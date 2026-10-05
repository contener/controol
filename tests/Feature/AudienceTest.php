<?php

namespace Tests\Feature;

use App\Models\AudienceInteraction;
use App\Models\AudienceMembre;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class AudienceTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function creerProduit(int $boutiqueId, array $overrides = []): Produit
    {
        return Produit::create(array_merge([
            'boutique_id' => $boutiqueId,
            'type' => 'produit',
            'nom' => 'Produit test',
            'prix_vente' => 1000,
            'unite' => 'pièce',
            'actif' => true,
            'marketplace_visible' => true,
        ], $overrides));
    }

    public function test_free_plan_sees_locked_audience_page_with_aggregates_only(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', []);
        $produit = $this->creerProduit($user->current_boutique_id);
        $this->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));

        $response = $this->actingAs($user)->get('/audience');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('autorise', false)
            ->where('statistiques.total', 1)
            ->where('membres', null));
    }

    public function test_paid_plan_sees_full_audience_list(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['audience' => true]);
        $produit = $this->creerProduit($user->current_boutique_id);
        $this->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));

        $response = $this->actingAs($user)->get('/audience');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('autorise', true)
            ->has('membres.data', 1));
    }

    public function test_user_cannot_view_another_boutiques_audience_member(): void
    {
        $userA = $this->creerUtilisateurAvecBoutique('pro');
        $userA->planActif()->update(['audience' => true]);
        $userB = $this->creerUtilisateurAvecBoutique('pro');
        $userB->planActif()->update(['audience' => true]);

        $produitB = $this->creerProduit($userB->current_boutique_id);
        $this->post(route('public.boutique.produits.jaime', [$userB->currentBoutique->slug, $produitB->id]));
        $membreB = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $userB->current_boutique_id)->firstOrFail();

        $this->actingAs($userA)->get("/audience/{$membreB->id}")->assertNotFound();
    }

    public function test_repeated_like_creates_only_one_member_and_one_interaction(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $produit = $this->creerProduit($user->current_boutique_id);
        $visiteur = User::factory()->create();

        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));

        $this->assertSame(1, AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->count());
        $this->assertSame(1, AudienceInteraction::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->where('type', 'LIKE')->count());
    }

    public function test_anonymous_visitor_like_is_tracked_via_cookie_token(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $produit = $this->creerProduit($user->current_boutique_id);

        $this->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]))->assertOk();

        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->first();
        $this->assertNotNull($membre);
        $this->assertNull($membre->user_id);
        $this->assertNotNull($membre->visiteur_token);
    }

    public function test_sending_a_message_about_a_product_creates_an_audience_entry(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $produit = $this->creerProduit($user->current_boutique_id);

        $this->post(route('public.boutique.messages.store', $user->currentBoutique->slug), [
            'nom_visiteur' => 'Jean Visiteur',
            'contenu' => 'Je suis intéressé par ce produit.',
            'produit_id' => $produit->id,
        ])->assertRedirect();

        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->first();
        $this->assertNotNull($membre);
        $this->assertSame('Jean Visiteur', $membre->nom);
        $this->assertSame(1, AudienceInteraction::withoutGlobalScopes()->where('type', 'MESSAGE')->where('produit_id', $produit->id)->count());
    }

    public function test_whatsapp_relaunch_builds_link_and_logs_interaction(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['audience' => true]);
        $produit = $this->creerProduit($user->current_boutique_id);
        $visiteur = User::factory()->create(['whatsapp' => '+237600000000']);

        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->firstOrFail();

        $response = $this->actingAs($user)->postJson("/audience/{$membre->id}/whatsapp", ['message' => 'Bonjour, toujours intéressé ?']);

        $response->assertOk();
        $response->assertJsonStructure(['lien']);
        $this->assertStringContainsString('237600000000', $response->json('lien'));
        $this->assertSame(1, AudienceInteraction::withoutGlobalScopes()->where('type', 'WHATSAPP_RELANCE_OPENED')->count());
    }

    public function test_whatsapp_relaunch_blocked_for_free_plan(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', []);
        $produit = $this->creerProduit($user->current_boutique_id);
        $visiteur = User::factory()->create(['whatsapp' => '+237600000000']);
        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->firstOrFail();

        $this->actingAs($user)->postJson("/audience/{$membre->id}/whatsapp", ['message' => 'Bonjour'])->assertForbidden();
    }

    public function test_plan_expiration_never_deletes_audience_data(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['audience' => true]);
        $produit = $this->creerProduit($user->current_boutique_id);
        $this->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));

        // Simule l'expiration : l'abonnement payant expire, l'utilisateur retombe sur Gratuit.
        $user->abonnements()->update(['date_fin' => now()->subDay()]);
        $this->assertNull($user->fresh()->planActif());

        $this->assertSame(1, AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->count());

        $response = $this->actingAs($user)->get('/audience');
        $response->assertInertia(fn ($page) => $page->where('autorise', false)->where('statistiques.total', 1));
    }

    public function test_message_relaunch_works_even_without_a_whatsapp_number(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['audience' => true]);
        $produit = $this->creerProduit($user->current_boutique_id);
        $visiteur = User::factory()->create(['whatsapp' => null]);

        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->firstOrFail();
        $this->assertNull($membre->contactAffiche());

        $response = $this->actingAs($user)->post("/audience/{$membre->id}/message", ['message' => 'Bonjour, toujours intéressé ?']);

        $response->assertRedirect();
        $conversation = Conversation::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->where('visiteur_user_id', $visiteur->id)->firstOrFail();
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame(ConversationMessage::EXPEDITEUR_BOUTIQUE, $conversation->messages()->first()->expediteur);
        $this->assertSame(1, AudienceInteraction::withoutGlobalScopes()->where('type', AudienceInteraction::MESSAGE_RELANCE_ENVOYE)->count());
    }

    public function test_message_relaunch_reuses_an_already_open_conversation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['audience' => true]);
        $produit = $this->creerProduit($user->current_boutique_id);
        $visiteur = User::factory()->create();

        $this->actingAs($visiteur)->post(route('public.boutique.messages.store', $user->currentBoutique->slug), [
            'nom_visiteur' => $visiteur->name,
            'contenu' => 'Bonjour, intéressé par ce produit.',
            'produit_id' => $produit->id,
        ]);
        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->firstOrFail();

        $this->actingAs($user)->post("/audience/{$membre->id}/message", ['message' => 'Toujours intéressé ?'])->assertRedirect();

        $this->assertSame(1, Conversation::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->count());
    }

    public function test_message_relaunch_blocked_for_free_plan(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', []);
        $produit = $this->creerProduit($user->current_boutique_id);
        $visiteur = User::factory()->create();
        $this->actingAs($visiteur)->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->firstOrFail();

        $this->actingAs($user)->post("/audience/{$membre->id}/message", ['message' => 'Bonjour'])->assertForbidden();
    }

    public function test_owner_can_update_a_member_status(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['audience' => true]);
        $produit = $this->creerProduit($user->current_boutique_id);
        $this->post(route('public.boutique.produits.jaime', [$user->currentBoutique->slug, $produit->id]));
        $membre = AudienceMembre::withoutGlobalScopes()->where('boutique_id', $user->current_boutique_id)->firstOrFail();

        $this->actingAs($user)->patch("/audience/{$membre->id}/statut", ['statut' => 'contacte'])->assertRedirect();

        $this->assertSame('contacte', $membre->fresh()->statut);
    }
}
