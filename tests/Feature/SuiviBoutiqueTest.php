<?php

namespace Tests\Feature;

use App\Models\NotificationUtilisateur;
use App\Models\Plan;
use App\Models\Suivi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\CreatesBoutique;
use Tests\TestCase;

class SuiviBoutiqueTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_authenticated_user_can_follow_and_unfollow_a_boutique(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $client = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($client)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre")->assertRedirect();

        $this->assertDatabaseHas('suivis_boutique', [
            'boutique_id' => $vendeur->currentBoutique->id,
            'user_id' => $client->id,
            'desabonne_a' => null,
        ]);
        $this->assertSame(1, $vendeur->currentBoutique->fresh()->nombreAbonnes());

        $this->actingAs($client)->delete("/boutique/{$vendeur->currentBoutique->slug}/suivre")->assertRedirect();

        $suivi = Suivi::where('boutique_id', $vendeur->currentBoutique->id)->where('user_id', $client->id)->firstOrFail();
        $this->assertNotNull($suivi->desabonne_a);
        $this->assertSame(0, $vendeur->currentBoutique->fresh()->nombreAbonnes());
    }

    public function test_follow_route_requires_authentication(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();

        $this->post("/boutique/{$vendeur->currentBoutique->slug}/suivre")->assertRedirect('/login');
        $this->assertDatabaseCount('suivis_boutique', 0);
    }

    public function test_owner_cannot_follow_their_own_boutique(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($vendeur)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre")->assertRedirect();

        $this->assertDatabaseCount('suivis_boutique', 0);
    }

    public function test_following_twice_never_creates_a_duplicate_row(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $client = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($client)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre");
        $this->actingAs($client)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre");

        $this->assertDatabaseCount('suivis_boutique', 1);
    }

    public function test_public_page_exposes_an_accurate_follower_count(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $abonne1 = $this->creerUtilisateurAvecBoutique();
        $abonne2 = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($abonne1)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre");
        $this->actingAs($abonne2)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre");
        $this->actingAs($abonne2)->delete("/boutique/{$vendeur->currentBoutique->slug}/suivre");

        $response = $this->get("/boutique/{$vendeur->currentBoutique->slug}");

        $response->assertInertia(fn ($page) => $page->component('Public/Boutique')->where('nombreAbonnes', 1));
    }

    public function test_popup_is_never_offered_to_a_visitor_who_already_owns_a_boutique(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $autreProprietaire = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($autreProprietaire)->get("/boutique/{$vendeur->currentBoutique->slug}");

        $response->assertInertia(fn ($page) => $page->where('visiteurABoutique', true));
    }

    public function test_popup_is_offered_to_a_logged_in_visitor_without_a_boutique(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $visiteur = User::factory()->create();

        $response = $this->actingAs($visiteur)->get("/boutique/{$vendeur->currentBoutique->slug}");

        $response->assertInertia(fn ($page) => $page->where('visiteurABoutique', false));
    }

    public function test_new_product_notifies_active_followers_with_notifications_enabled(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $boutique = $vendeur->currentBoutique;

        $abonneActif = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($abonneActif)->post("/boutique/{$boutique->slug}/suivre");

        $abonneNotifsCoupees = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($abonneNotifsCoupees)->post("/boutique/{$boutique->slug}/suivre");
        Suivi::where('user_id', $abonneNotifsCoupees->id)->update(['notifications_actives' => false]);

        $abonneDesabonne = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($abonneDesabonne)->post("/boutique/{$boutique->slug}/suivre");
        $this->actingAs($abonneDesabonne)->delete("/boutique/{$boutique->slug}/suivre");

        $this->actingAs($vendeur)->post('/produits', [
            'type' => 'produit',
            'nom' => 'Nouveau produit',
            'prix_vente' => 5000,
            'unite' => 'unité',
            'gere_stock' => false,
            'mini_characteristics' => 'Rouge, taille M',
        ])->assertRedirect();

        $this->assertDatabaseCount('notifications_utilisateurs', 1);

        $notification = NotificationUtilisateur::where('user_id', $abonneActif->id)->firstOrFail();
        $this->assertSame('nouveau_produit', $notification->type);
        $this->assertStringContainsString('Nouveau produit', $notification->message);
        $this->assertStringContainsString('Rouge, taille M', $notification->message);
        $this->assertSame(route('public.boutique', $boutique->slug), $notification->lien);
    }

    public function test_inactive_product_does_not_notify_followers(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();
        $abonne = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($abonne)->post("/boutique/{$vendeur->currentBoutique->slug}/suivre");

        $this->actingAs($vendeur)->post('/produits', [
            'type' => 'produit',
            'nom' => 'Produit inactif',
            'prix_vente' => 1000,
            'unite' => 'unité',
            'gere_stock' => false,
            'actif' => false,
        ]);

        $this->assertDatabaseCount('notifications_utilisateurs', 0);
    }

    public function test_capturing_middleware_stores_the_invitation_boutique_in_session(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();

        $response = $this->get('/register?boutique='.$vendeur->currentBoutique->slug);

        $response->assertSessionHas('invitation_boutique_slug', $vendeur->currentBoutique->slug);
    }

    public function test_registration_creates_the_follow_relationship_regardless_of_which_button_was_used(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique();

        // Peu importe le bouton cliqué sur la page boutique (popup, bandeau du bas...) :
        // seule la présence de la boutique d'origine en session compte.
        $response = $this->withSession([
            'invitation_boutique_slug' => $vendeur->currentBoutique->slug,
        ])->post('/register', [
            'name' => 'Nouveau Fan',
            'email' => 'fan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $response->assertRedirect();

        $nouvelUtilisateur = User::where('email', 'fan@example.com')->firstOrFail();
        $this->assertDatabaseHas('suivis_boutique', [
            'boutique_id' => $vendeur->currentBoutique->id,
            'user_id' => $nouvelUtilisateur->id,
        ]);
        $this->assertDatabaseHas('evenements_invitation_boutique', [
            'boutique_id' => $vendeur->currentBoutique->id,
            'user_id' => $nouvelUtilisateur->id,
            'type_evenement' => 'compte_cree',
        ]);
        $this->assertDatabaseHas('evenements_invitation_boutique', [
            'boutique_id' => $vendeur->currentBoutique->id,
            'user_id' => $nouvelUtilisateur->id,
            'type_evenement' => 'abonnement_cree',
        ]);
    }

    public function test_registration_without_any_invitation_never_creates_a_follow_relationship(): void
    {
        $this->post('/register', [
            'name' => 'Inscription Organique',
            'email' => 'organique@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertDatabaseCount('suivis_boutique', 0);
        $this->assertDatabaseCount('evenements_invitation_boutique', 0);
    }

    public function test_creating_a_boutique_from_an_invitation_creates_the_follow_relationship(): void
    {
        $vendeurOrigine = $this->creerUtilisateurAvecBoutique();

        $nouveauVendeur = User::factory()->create();
        $planGratuit = Plan::firstOrCreate(['code' => 'gratuit'], ['nom' => 'Gratuit', 'prix' => 0]);
        $nouveauVendeur->abonnements()->create(['plan_id' => $planGratuit->id, 'statut' => 'actif', 'date_debut' => now()]);

        // Simule le bandeau "Créer ma boutique gratuitement" (pas le popup) : la
        // boutique d'origine doit quand même recevoir son abonné.
        $response = $this->actingAs($nouveauVendeur)->withSession([
            'invitation_boutique_slug' => $vendeurOrigine->currentBoutique->slug,
        ])->post('/boutiques', [
            'nom' => 'Ma Nouvelle Boutique',
            'devise' => 'XAF',
        ]);

        $response->assertRedirect();

        $nouvelleBoutique = $nouveauVendeur->fresh()->currentBoutique;

        $this->assertDatabaseHas('suivis_boutique', [
            'boutique_id' => $vendeurOrigine->currentBoutique->id,
            'user_id' => $nouveauVendeur->id,
        ]);
        $this->assertDatabaseHas('evenements_invitation_boutique', [
            'boutique_id' => $vendeurOrigine->currentBoutique->id,
            'user_id' => $nouveauVendeur->id,
            'type_evenement' => 'boutique_creee',
        ]);
        $this->assertNotSame($vendeurOrigine->currentBoutique->id, $nouvelleBoutique->id);
    }
}
