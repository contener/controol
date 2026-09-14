<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\WhatsAppAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class WhatsAppAgentTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_visiting_the_page_automatically_creates_the_agent_for_the_current_boutique(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();

        $this->assertDatabaseCount('whatsapp_agents', 0);

        $response = $this->actingAs($user)->get(route('whatsapp-agent.show'));

        $response->assertOk();
        $this->assertDatabaseHas('whatsapp_agents', ['boutique_id' => $user->current_boutique_id, 'actif' => false]);
        $response->assertInertia(fn ($page) => $page
            ->component('WhatsappAgent/Show')
            ->where('boutique.id', $user->current_boutique_id)
            ->where('agent.nom', 'Assistant'));
    }

    public function test_two_boutiques_have_distinct_agents_with_no_cross_tenant_leakage(): void
    {
        $userA = $this->creerUtilisateurAvecBoutique('pro', ['nom' => 'Boutique A']);
        $userB = $this->creerUtilisateurAvecBoutique('pro', ['nom' => 'Boutique B']);

        $this->actingAs($userA)->get(route('whatsapp-agent.show'))->assertOk();
        $this->actingAs($userB)->get(route('whatsapp-agent.show'))->assertOk();

        $this->assertDatabaseCount('whatsapp_agents', 2);

        $agentA = WhatsAppAgent::where('boutique_id', $userA->current_boutique_id)->firstOrFail();
        $agentB = WhatsAppAgent::where('boutique_id', $userB->current_boutique_id)->firstOrFail();
        $this->assertNotSame($agentA->id, $agentB->id);

        // Depuis le contexte de A, seul l'agent de A doit jamais être visible/atteignable.
        $reponse = $this->actingAs($userA)->get(route('whatsapp-agent.show'));
        $reponse->assertInertia(fn ($page) => $page->where('agent.id', $agentA->id));
    }

    public function test_toggle_statut_silently_refuses_activation_without_the_plan_flag(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $this->actingAs($user)->get(route('whatsapp-agent.show'));

        $response = $this->actingAs($user)->patch(route('whatsapp-agent.statut'), ['actif' => true]);

        $response->assertRedirect();
        $agent = WhatsAppAgent::where('boutique_id', $user->current_boutique_id)->firstOrFail();
        $this->assertFalse($agent->actif, "L'activation ne doit jamais être acceptée sans le flag chatbot_whatsapp du plan.");
    }

    public function test_toggle_statut_activates_when_plan_allows_it(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['chatbot_whatsapp' => true]);
        $this->actingAs($user)->get(route('whatsapp-agent.show'));

        $this->actingAs($user)->patch(route('whatsapp-agent.statut'), ['actif' => true])->assertRedirect();

        $agent = WhatsAppAgent::where('boutique_id', $user->current_boutique_id)->firstOrFail();
        $this->assertTrue($agent->actif);
    }

    public function test_user_cannot_open_the_agent_of_another_users_boutique(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique();
        $intrus = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($intrus)->post(route('whatsapp-agent.ouvrir', $proprietaire->currentBoutique));

        $response->assertStatus(403);
    }

    public function test_opening_the_agent_from_a_non_current_boutique_card_switches_boutique_first(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro', ['nom' => 'Boutique A']);
        $boutiqueB = Boutique::create([
            'user_id' => $user->id, 'nom' => 'Boutique B', 'slug' => 'boutique-b-'.uniqid(),
            'statut' => 'active', 'devise' => 'XAF',
        ]);

        $this->assertNotSame($boutiqueB->id, $user->current_boutique_id);

        $this->actingAs($user)->post(route('whatsapp-agent.ouvrir', $boutiqueB))->assertRedirect(route('whatsapp-agent.show'));

        $this->assertSame($boutiqueB->id, $user->fresh()->current_boutique_id);
    }

    public function test_configuration_update_persists(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user)->get(route('whatsapp-agent.show'));

        $this->actingAs($user)->patch(route('whatsapp-agent.update'), [
            'nom' => 'Sarah',
            'langue' => 'en',
            'personnalite' => 'Chaleureuse',
            'ton' => 'Amical',
            'message_accueil' => 'Bonjour, comment puis-je vous aider ?',
            'message_hors_horaires' => 'Nous sommes actuellement fermés.',
        ])->assertRedirect();

        $agent = WhatsAppAgent::where('boutique_id', $user->current_boutique_id)->firstOrFail();
        $this->assertSame('Sarah', $agent->nom);
        $this->assertSame('en', $agent->langue);
        $this->assertSame('Chaleureuse', $agent->personnalite);
        $this->assertSame('Bonjour, comment puis-je vous aider ?', $agent->message_accueil);
    }
}
