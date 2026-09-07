<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Produit;
use App\Services\VisiteurIdentiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ConversationTest extends TestCase
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

    public function test_visitor_can_send_a_first_message_and_it_creates_a_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $response = $this->post("/boutique/{$boutique->slug}/messages", [
            'nom_visiteur' => 'Jean Visiteur',
            'contenu' => 'Bonjour, est-ce disponible ?',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('conversations', [
            'boutique_id' => $boutique->id,
            'visiteur_nom' => 'Jean Visiteur',
            'statut' => Conversation::STATUT_OUVERTE,
            'messages_non_lus_boutique' => 1,
        ]);
        $this->assertDatabaseHas('conversation_messages', [
            'expediteur' => ConversationMessage::EXPEDITEUR_VISITEUR,
            'contenu' => 'Bonjour, est-ce disponible ?',
        ]);
    }

    public function test_follow_up_message_from_same_visitor_reuses_the_open_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $premiere = $this->post("/boutique/{$boutique->slug}/messages", [
            'nom_visiteur' => 'Jean Visiteur',
            'contenu' => 'Premier message.',
        ]);
        $cookie = $premiere->getCookie(VisiteurIdentiteService::COOKIE_TOKEN)->getValue();

        $this->withCookie(VisiteurIdentiteService::COOKIE_TOKEN, $cookie)->post("/boutique/{$boutique->slug}/messages", [
            'nom_visiteur' => 'Jean Visiteur',
            'contenu' => 'Deuxième message.',
        ]);

        $this->assertSame(1, Conversation::withoutGlobalScopes()->where('boutique_id', $boutique->id)->count());
        $this->assertSame(2, ConversationMessage::count());
        $this->assertDatabaseHas('conversations', ['boutique_id' => $boutique->id, 'messages_non_lus_boutique' => 2]);
    }

    public function test_guest_cookie_is_set_on_first_message_and_reused_on_second_request(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $response = $this->post("/boutique/{$boutique->slug}/messages", [
            'nom_visiteur' => 'Visiteur',
            'contenu' => 'Message.',
        ]);

        $this->assertNotNull($response->getCookie(VisiteurIdentiteService::COOKIE_TOKEN));
    }

    public function test_forged_produit_id_from_another_boutique_is_ignored(): void
    {
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $boutiqueA = $vendeurA->currentBoutique;

        $vendeurB = $this->creerUtilisateurAvecBoutique();
        $produitB = Produit::create([
            'boutique_id' => $vendeurB->currentBoutique->id,
            'type' => 'produit',
            'nom' => 'Produit de B',
            'prix_vente' => 1000,
            'unite' => 'unité',
        ]);

        $response = $this->post("/boutique/{$boutiqueA->slug}/messages", [
            'nom_visiteur' => 'Visiteur',
            'contenu' => 'Message sur un produit forgé.',
            'produit_id' => $produitB->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('conversations', ['boutique_id' => $boutiqueA->id, 'produit_id' => null]);
    }

    public function test_message_validation_requires_name_and_content(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $response = $this->post("/boutique/{$boutique->slug}/messages", []);

        $response->assertSessionHasErrors(['nom_visiteur', 'contenu']);
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_message_endpoint_is_rate_limited(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        for ($i = 0; $i < 5; $i++) {
            $this->post("/boutique/{$boutique->slug}/messages", ['nom_visiteur' => 'V', 'contenu' => "Message {$i}"]);
        }

        $response = $this->post("/boutique/{$boutique->slug}/messages", ['nom_visiteur' => 'V', 'contenu' => 'En trop']);

        $response->assertStatus(429);
    }

    public function test_signed_guest_link_grants_access_to_the_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $url = URL::signedRoute('public.conversations.show', ['conversation' => $conversation->id]);

        $this->get($url)->assertOk();
    }

    public function test_tampered_guest_link_signature_is_rejected(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $url = URL::signedRoute('public.conversations.show', ['conversation' => $conversation->id]);
        $url = preg_replace('/signature=[a-f0-9]+/', 'signature=0000000000000000000000000000000000000000000000000000000000000000', $url);

        $this->get($url)->assertForbidden();
    }

    public function test_expired_guest_link_signature_is_rejected(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $url = URL::temporarySignedRoute('public.conversations.show', now()->subDay(), ['conversation' => $conversation->id]);

        $this->get($url)->assertForbidden();
    }

    public function test_guest_can_reply_via_signed_link_page(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $url = URL::signedRoute('public.conversations.show', ['conversation' => $conversation->id]);
        $ouverture = $this->get($url);
        $cookie = $ouverture->getCookie(VisiteurIdentiteService::COOKIE_TOKEN)->getValue();

        $response = $this->withCookie(VisiteurIdentiteService::COOKIE_TOKEN, $cookie)
            ->post("/conversations/{$conversation->id}/repondre", ['contenu' => 'Merci pour votre réponse.']);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_messages', ['conversation_id' => $conversation->id, 'contenu' => 'Merci pour votre réponse.']);
    }

    public function test_boutique_owner_can_view_their_conversations(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->creerConversation($user->currentBoutique->id, ['visiteur_nom' => 'Client potentiel']);

        $response = $this->actingAs($user)->get('/messages');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Messages/Index')
            ->has('conversations.data', 1)
            ->where('conversations.data.0.visiteur_nom', 'Client potentiel'));
    }

    public function test_boutique_owner_cannot_see_another_boutiques_conversations(): void
    {
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $vendeurB = $this->creerUtilisateurAvecBoutique();
        $this->creerConversation($vendeurB->currentBoutique->id);

        $response = $this->actingAs($vendeurA)->get('/messages');

        $response->assertInertia(fn ($page) => $page->component('Messages/Index')->has('conversations.data', 0));
    }

    public function test_opening_a_conversation_marks_it_as_read_for_the_owner(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id, ['messages_non_lus_boutique' => 3]);

        $this->actingAs($user)->get('/messages?conversation='.$conversation->id);

        $this->assertSame(0, $conversation->fresh()->messages_non_lus_boutique);
    }

    public function test_owner_can_reply_to_a_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $response = $this->actingAs($user)->post("/messages/{$conversation->id}/repondre", ['contenu' => 'Oui, disponible.']);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversation_messages', ['conversation_id' => $conversation->id, 'expediteur' => 'boutique', 'contenu' => 'Oui, disponible.']);
        $this->assertSame(1, $conversation->fresh()->messages_non_lus_visiteur);
    }

    public function test_owner_cannot_reply_to_another_boutiques_conversation(): void
    {
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $vendeurB = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($vendeurB->currentBoutique->id);

        $response = $this->actingAs($vendeurA)->post("/messages/{$conversation->id}/repondre", ['contenu' => 'Intrusion.']);

        $response->assertNotFound();
    }

    public function test_owner_can_archive_a_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $this->actingAs($user)->patch("/messages/{$conversation->id}/statut", ['statut' => 'archivee']);

        $this->assertSame('archivee', $conversation->fresh()->statut);
    }

    public function test_owner_can_close_a_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $this->actingAs($user)->patch("/messages/{$conversation->id}/statut", ['statut' => 'fermee']);

        $this->assertSame('fermee', $conversation->fresh()->statut);
    }

    public function test_owner_can_reopen_an_archived_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id, ['statut' => 'archivee']);

        $this->actingAs($user)->patch("/messages/{$conversation->id}/statut", ['statut' => 'ouverte']);

        $this->assertSame('ouverte', $conversation->fresh()->statut);
    }

    public function test_owner_can_block_a_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $conversation = $this->creerConversation($user->currentBoutique->id);

        $this->actingAs($user)->patch("/messages/{$conversation->id}/statut", ['statut' => 'bloquee']);

        $this->assertSame('bloquee', $conversation->fresh()->statut);
    }

    public function test_blocked_visitor_cannot_send_a_new_message_to_the_boutique(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $premiere = $this->post("/boutique/{$boutique->slug}/messages", ['nom_visiteur' => 'Spammeur', 'contenu' => 'Spam.']);
        $cookie = $premiere->getCookie(VisiteurIdentiteService::COOKIE_TOKEN)->getValue();

        Conversation::withoutGlobalScopes()->where('boutique_id', $boutique->id)->update(['statut' => 'bloquee']);

        $response = $this->withCookie(VisiteurIdentiteService::COOKIE_TOKEN, $cookie)
            ->post("/boutique/{$boutique->slug}/messages", ['nom_visiteur' => 'Spammeur', 'contenu' => 'Encore.']);

        $response->assertSessionHas('flash_error');
        $this->assertSame(1, ConversationMessage::count());
    }

    public function test_owner_can_start_a_conversation_with_an_existing_client(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $client = Client::create(['boutique_id' => $user->currentBoutique->id, 'nom' => 'Alpha SARL', 'email' => 'alpha@example.com']);

        $response = $this->actingAs($user)->post('/messages', ['client_id' => $client->id, 'contenu' => 'Bonjour Alpha.']);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversations', ['boutique_id' => $user->currentBoutique->id, 'client_id' => $client->id, 'visiteur_nom' => 'Alpha SARL']);
    }

    public function test_owner_cannot_start_a_conversation_with_another_boutiques_client(): void
    {
        $vendeurA = $this->creerUtilisateurAvecBoutique();
        $vendeurB = $this->creerUtilisateurAvecBoutique();
        $clientDeB = Client::create(['boutique_id' => $vendeurB->currentBoutique->id, 'nom' => 'Client de B']);

        $response = $this->actingAs($vendeurA)->post('/messages', ['client_id' => $clientDeB->id, 'contenu' => 'Intrusion.']);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_authenticated_visitor_identity_is_used_instead_of_a_cookie(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $acheteur = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($acheteur)->post("/boutique/{$vendeur->currentBoutique->slug}/messages", [
            'nom_visiteur' => $acheteur->name,
            'contenu' => 'Intéressé.',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('conversations', [
            'boutique_id' => $vendeur->currentBoutique->id,
            'visiteur_user_id' => $acheteur->id,
        ]);
        $this->assertNull($response->getCookie(VisiteurIdentiteService::COOKIE_TOKEN));
    }
}
