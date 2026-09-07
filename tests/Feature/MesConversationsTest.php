<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class MesConversationsTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function creerConversation(int $boutiqueId, array $overrides = []): Conversation
    {
        return Conversation::withoutGlobalScopes()->create(array_merge([
            'boutique_id' => $boutiqueId,
            'visiteur_nom' => 'Visiteur Test',
            'statut' => Conversation::STATUT_OUVERTE,
        ], $overrides));
    }

    public function test_authenticated_user_can_see_conversations_they_started_as_a_visitor(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $acheteur = $this->creerUtilisateurAvecBoutique();
        $this->creerConversation($vendeur->currentBoutique->id, ['visiteur_user_id' => $acheteur->id, 'visiteur_nom' => $acheteur->name]);

        $response = $this->actingAs($acheteur)->get('/mes-conversations');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('MesConversations/Index')->has('conversations.data', 1));
    }

    public function test_my_conversations_are_scoped_to_the_authenticated_visitor_only(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $acheteurA = $this->creerUtilisateurAvecBoutique();
        $acheteurB = $this->creerUtilisateurAvecBoutique();
        $this->creerConversation($vendeur->currentBoutique->id, ['visiteur_user_id' => $acheteurB->id, 'visiteur_nom' => $acheteurB->name]);

        $response = $this->actingAs($acheteurA)->get('/mes-conversations');

        $response->assertInertia(fn ($page) => $page->component('MesConversations/Index')->has('conversations.data', 0));
    }

    public function test_visitor_can_reply_from_their_conversations_page(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $acheteur = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($vendeur->currentBoutique->id, ['visiteur_user_id' => $acheteur->id, 'visiteur_nom' => $acheteur->name]);

        $response = $this->actingAs($acheteur)->post("/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Toujours intéressé.']);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_messages', ['conversation_id' => $conversation->id, 'expediteur' => 'visiteur', 'contenu' => 'Toujours intéressé.']);
    }

    public function test_visitor_cannot_reply_to_a_conversation_that_is_not_theirs(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $acheteurA = $this->creerUtilisateurAvecBoutique();
        $acheteurB = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($vendeur->currentBoutique->id, ['visiteur_user_id' => $acheteurB->id, 'visiteur_nom' => $acheteurB->name]);

        $response = $this->actingAs($acheteurA)->post("/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Intrusion.']);

        $response->assertForbidden();
    }
}
