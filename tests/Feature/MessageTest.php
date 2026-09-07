<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\CreatesBoutique;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_visitor_can_send_a_message_to_the_seller(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $response = $this->post("/boutique/{$boutique->slug}/message", [
            'nom_visiteur' => 'Jean Visiteur',
            'contact_visiteur' => '+237600000000',
            'contenu' => 'Bonjour, est-ce disponible en bleu ?',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('messages', [
            'boutique_id' => $boutique->id,
            'nom_visiteur' => 'Jean Visiteur',
            'contact_visiteur' => '+237600000000',
            'contenu' => 'Bonjour, est-ce disponible en bleu ?',
            'lu' => false,
        ]);
    }

    public function test_message_validation_requires_name_and_content(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $response = $this->post("/boutique/{$boutique->slug}/message", []);

        $response->assertSessionHasErrors(['nom_visiteur', 'contenu']);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_forged_produit_id_from_another_boutique_is_ignored(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $boutiqueA = $vendeurA->currentBoutique;

        $vendeurB = $this->creerUtilisateurAvecBoutique();
        $boutiqueB = $vendeurB->currentBoutique;
        $produitB = Produit::create([
            'boutique_id' => $boutiqueB->id,
            'type' => 'produit',
            'nom' => 'Produit de B',
            'prix_vente' => 1000,
            'unite' => 'unité',
        ]);

        $response = $this->post("/boutique/{$boutiqueA->slug}/message", [
            'nom_visiteur' => 'Visiteur',
            'contenu' => 'Message sur un produit forgé.',
            'produit_id' => $produitB->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('messages', [
            'boutique_id' => $boutiqueA->id,
            'produit_id' => null,
        ]);
    }

    public function test_boutique_owner_can_view_their_messages(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;
        Message::create([
            'boutique_id' => $boutique->id,
            'nom_visiteur' => 'Client potentiel',
            'contenu' => 'Avez-vous ce produit en stock ?',
        ]);

        $response = $this->actingAs($user)->get('/messages');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Messages/Index')
            ->has('messages.data', 1)
            ->where('messages.data.0.nom_visiteur', 'Client potentiel'));
    }

    public function test_boutique_owner_cannot_see_another_boutiques_messages(): void
    {
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $vendeurB = $this->creerUtilisateurAvecBoutique();
        Message::create([
            'boutique_id' => $vendeurB->currentBoutique->id,
            'nom_visiteur' => 'Client de B',
            'contenu' => 'Message pour B.',
        ]);

        $response = $this->actingAs($vendeurA)->get('/messages');

        $response->assertInertia(fn ($page) => $page->component('Messages/Index')->has('messages.data', 0));
    }

    public function test_owner_can_mark_message_as_read(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $message = Message::create([
            'boutique_id' => $user->currentBoutique->id,
            'nom_visiteur' => 'Client',
            'contenu' => 'Bonjour.',
        ]);

        $response = $this->actingAs($user)->patch("/messages/{$message->id}/lu");

        $response->assertRedirect();
        $this->assertTrue($message->fresh()->lu);
    }

    public function test_owner_cannot_mark_another_boutiques_message_as_read(): void
    {
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $vendeurB = $this->creerUtilisateurAvecBoutique();
        $message = Message::create([
            'boutique_id' => $vendeurB->currentBoutique->id,
            'nom_visiteur' => 'Client de B',
            'contenu' => 'Message pour B.',
        ]);

        $response = $this->actingAs($vendeurA)->patch("/messages/{$message->id}/lu");

        $response->assertNotFound();
        $this->assertFalse($message->fresh()->lu);
    }

    public function test_owner_can_delete_a_message(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $message = Message::create([
            'boutique_id' => $user->currentBoutique->id,
            'nom_visiteur' => 'Client',
            'contenu' => 'Bonjour.',
        ]);

        $response = $this->actingAs($user)->delete("/messages/{$message->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_guest_cannot_access_messages_management(): void
    {
        $response = $this->get('/messages');

        $response->assertRedirect(route('login'));
    }

    public function test_message_endpoint_is_rate_limited(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        for ($i = 0; $i < 5; $i++) {
            $this->post("/boutique/{$boutique->slug}/message", [
                'nom_visiteur' => 'Visiteur',
                'contenu' => 'Message numéro '.$i,
            ]);
        }

        $response = $this->post("/boutique/{$boutique->slug}/message", [
            'nom_visiteur' => 'Visiteur',
            'contenu' => 'Message en trop.',
        ]);

        $response->assertStatus(429);
    }
}
