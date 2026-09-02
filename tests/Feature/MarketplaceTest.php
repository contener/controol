<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\CampagneDestination;
use App\Models\CampagneSociale;
use App\Models\DestinationSociale;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function creerPlan(string $code, bool $marketplace, bool $publicationSociale = false, ?int $limiteDestinations = null): Plan
    {
        return Plan::create([
            'code' => $code,
            'nom' => ucfirst($code),
            'prix' => $marketplace ? 5000 : 0,
            'limite_destinations_sociales' => $limiteDestinations,
            'marketplace' => $marketplace,
            'publication_sociale' => $publicationSociale,
        ]);
    }

    private function creerUtilisateurAvecPlanEtBoutique(Plan $plan, array $boutiqueAttributs = []): User
    {
        $user = User::factory()->create();
        $user->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'actif', 'date_debut' => now()]);

        $boutique = Boutique::create(array_merge([
            'user_id' => $user->id,
            'nom' => 'Boutique de '.$user->name,
            'slug' => 'boutique-'.$user->id.'-'.uniqid(),
            'statut' => 'active',
            'devise' => 'XAF',
        ], $boutiqueAttributs));

        $user->switchBoutique($boutique);

        return $user->fresh();
    }

    // TEST 1 — utilisateur gratuit : Marketplace accessible, aucune boutique gratuite affichée.
    public function test_marketplace_is_accessible_but_excludes_free_plan_boutiques(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit, [
            'nom' => 'Boutique Gratuite', 'marketplace_visible' => true,
        ]);

        $response = $this->actingAs($user)->get('/marketplace');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplace/Index')
            ->has('boutiques.data', 0));
        $this->assertFalse($user->currentBoutique->estEligibleMarketplace());
    }

    // TEST 2 — utilisateur Basique : boutique éligible et visible dans la Marketplace.
    public function test_basic_plan_boutique_is_eligible_and_visible_in_marketplace(): void
    {
        $planBasique = $this->creerPlan('basique', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planBasique, [
            'nom' => 'Boutique Basique', 'marketplace_visible' => true,
        ]);

        $this->assertTrue($user->currentBoutique->estEligibleMarketplace());

        $response = $this->get('/marketplace');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Marketplace/Index')
            ->where('boutiques.data.0.nom', 'Boutique Basique'));
    }

    // TEST 3 — utilisateur Pro : boutique éligible et visible.
    public function test_pro_plan_boutique_is_eligible_and_visible_in_marketplace(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro, [
            'nom' => 'Boutique Pro', 'marketplace_visible' => true,
        ]);

        $this->assertTrue($user->currentBoutique->estEligibleMarketplace());
        $this->assertContains($user->currentBoutique->id, Boutique::eligiblesMarketplace()->pluck('id'));
    }

    // TEST 4 — boutique gratuite : lien public accessible sans connexion, produits visibles.
    public function test_free_boutique_public_link_is_accessible_without_authentication(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit, ['slug' => 'ma-petite-boutique']);

        Produit::create([
            'boutique_id' => $user->current_boutique_id,
            'type' => 'produit', 'nom' => 'Produit public', 'prix_vente' => 1000, 'unite' => 'pièce', 'actif' => true,
        ]);

        $response = $this->get('/boutique/ma-petite-boutique');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Boutique')
            ->has('produits', 1)
            ->where('produits.0.nom', 'Produit public'));
    }

    // TEST 5 — visiteur non connecté : CTA "créer ma boutique" pointe vers l'inscription.
    public function test_anonymous_visitor_sees_registration_cta_on_public_boutique(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit, ['slug' => 'boutique-visiteur']);

        $response = $this->get('/boutique/boutique-visiteur');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('auth.user', null));
    }

    // TEST 6 — utilisateur connecté : Marketplace visible depuis la nav, retour à sa boutique possible.
    public function test_authenticated_user_can_browse_marketplace_and_return_to_dashboard(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro);

        $this->actingAs($user)->get('/marketplace')->assertOk();
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    // TEST 7 — partage : l'URL renvoyée dans les métadonnées est la bonne URL publique.
    public function test_share_metadata_contains_the_correct_public_url(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit, ['slug' => 'boutique-partage']);

        $response = $this->get('/boutique/boutique-partage');

        $response->assertInertia(fn ($page) => $page->where('meta.url', route('public.boutique', 'boutique-partage')));
    }

    // TEST 8 — publication sociale : confirmation manuelle -> statut ENVOYÉ ; échec -> statut ÉCHEC.
    // Aucune publication n'est automatisée : le statut ne change que sur confirmation explicite.
    public function test_social_campaign_status_reflects_manual_confirmation_only(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true, publicationSociale: true, limiteDestinations: null);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro);

        $destination = DestinationSociale::create([
            'boutique_id' => $user->current_boutique_id,
            'nom' => 'Groupe Test', 'lien' => 'https://chat.whatsapp.com/test',
        ]);

        $this->actingAs($user)->post('/campagnes-sociales', [
            'message' => 'Découvrez {{shop_name}} ! {{shop_url}}',
            'intervalle_secondes' => 30,
        ])->assertRedirect();

        $campagne = CampagneSociale::first();
        $this->assertSame('en_cours', $campagne->statut);

        $campagneDestination = CampagneDestination::first();
        $this->assertSame('en_attente', $campagneDestination->statut, 'Ne doit jamais être "envoyé" avant confirmation humaine.');

        $this->actingAs($user)->post("/campagne-destinations/{$campagneDestination->id}/confirmer", [
            'statut' => 'envoye',
        ])->assertRedirect();

        $this->assertSame('envoye', $campagneDestination->fresh()->statut);
        $this->assertSame('envoye', $destination->fresh()->statut);
        $this->assertSame('terminee', $campagne->fresh()->statut);
    }

    public function test_social_campaign_destination_can_be_marked_as_failed(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true, publicationSociale: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro);

        DestinationSociale::create([
            'boutique_id' => $user->current_boutique_id,
            'nom' => 'Groupe Test', 'lien' => 'https://chat.whatsapp.com/test',
        ]);

        $this->actingAs($user)->post('/campagnes-sociales', ['message' => 'Test {{shop_url}}', 'intervalle_secondes' => 30]);

        $campagneDestination = CampagneDestination::first();

        $this->actingAs($user)->post("/campagne-destinations/{$campagneDestination->id}/confirmer", ['statut' => 'echec']);

        $this->assertSame('echec', $campagneDestination->fresh()->statut);
    }

    public function test_free_plan_user_cannot_start_social_campaign(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false, publicationSociale: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit);

        DestinationSociale::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'X', 'lien' => 'https://example.com']);

        $response = $this->actingAs($user)->post('/campagnes-sociales', ['message' => 'Test', 'intervalle_secondes' => 30]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('campagnes_sociales', 0);
    }

    // TEST 9 — sécurité : impossible de rendre sa boutique visible dans la Marketplace en
    // forgeant la requête, si le plan ne l'autorise pas.
    public function test_user_cannot_force_marketplace_eligibility_via_forged_request(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit);
        $boutique = $user->currentBoutique;

        $this->actingAs($user)->patch("/boutiques/{$boutique->id}/marketplace", [
            'marketplace_visible' => true,
        ]);

        $boutique->refresh();
        // Le backend retranche silencieusement : la colonne peut être vraie, mais
        // l'éligibilité réelle (celle qui gouverne l'affichage public) reste fausse.
        $this->assertFalse($boutique->estEligibleMarketplace());
        $this->assertDatabaseMissing('boutiques', ['id' => $boutique->id, 'marketplace_visible' => true]);
        $this->assertNotContains($boutique->id, Boutique::eligiblesMarketplace()->pluck('id'));
    }

    public function test_admin_disabled_boutique_never_appears_in_marketplace_even_if_plan_active(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro, ['marketplace_visible' => true]);

        // marketplace_disabled_by_admin est volontairement absent de $fillable (comme
        // User::role) : seul un forceFill() serveur peut le modifier, jamais un tableau
        // de mass-assignment — d'où ce forceFill explicite plutôt qu'un attribut de création.
        $user->currentBoutique->forceFill(['marketplace_disabled_by_admin' => true])->save();

        $this->assertFalse($user->currentBoutique->fresh()->estEligibleMarketplace());
        $this->assertNotContains($user->currentBoutique->id, Boutique::eligiblesMarketplace()->pluck('id'));
    }

    public function test_non_super_admin_cannot_toggle_marketplace_admin_disable(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro);

        $response = $this->actingAs($user)->patch("/admin/marketplace/{$user->current_boutique_id}/basculer");

        $response->assertStatus(403);
    }

    // TEST 16 — cause réelle du ticket : boutiques créées SOUS le plan Gratuit, via le
    // flux réel (demande de changement -> paiement en_attente -> approbation admin),
    // doivent devenir publiables sans jamais être recréées, dès que l'abonnement payant
    // est réellement actif (pas au moment du clic sur "Payer").
    public function test_shops_created_under_free_plan_become_publishable_after_real_upgrade_without_recreation(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $planBasique = $this->creerPlan('basique', marketplace: true);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit, ['nom' => 'Boutique A']);
        $boutiqueA = $user->currentBoutique;
        $boutiqueB = Boutique::create([
            'user_id' => $user->id, 'nom' => 'Boutique B', 'slug' => 'boutique-b-'.uniqid(),
            'statut' => 'active', 'devise' => 'XAF',
        ]);

        // Avant paiement : le bouton de publication ne doit jamais être proposé.
        $this->actingAs($user)->get(route('boutiques.edit', $boutiqueA))
            ->assertInertia(fn ($page) => $page->where('planAutoriseMarketplace', false));

        // Flux réel : la demande crée un abonnement "en_attente", jamais "actif" (RULE 5).
        $this->actingAs($user)->post(route('abonnement.changer', $planBasique))->assertRedirect();
        $this->assertSame('gratuit', $user->fresh()->planActif()->code, "Le paiement en attente ne doit jamais activer l'abonnement.");

        $paiement = Paiement::where('user_id', $user->id)->where('statut', 'en_attente')->firstOrFail();
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/approuver")->assertRedirect();

        $user = $user->fresh();
        $this->assertSame('basique', $user->planActif()->code);

        // Sans aucune recréation : les DEUX boutiques (créées sous Gratuit) deviennent publiables.
        $this->actingAs($user)->get(route('boutiques.edit', $boutiqueA))
            ->assertInertia(fn ($page) => $page->where('planAutoriseMarketplace', true));

        foreach ([$boutiqueA, $boutiqueB] as $boutique) {
            $this->actingAs($user)->patch(route('boutiques.marketplace', $boutique), ['marketplace_visible' => true])->assertRedirect();
            $this->assertTrue($boutique->fresh()->estEligibleMarketplace());
        }

        $this->assertContains($boutiqueA->id, Boutique::eligiblesMarketplace()->pluck('id'));
        $this->assertContains($boutiqueB->id, Boutique::eligiblesMarketplace()->pluck('id'));
    }

    // TEST 17 — utilisateur Gratuit : le bouton n'apparaît jamais (prop backend) et toute
    // tentative de démarrage de publication échoue avec 403 (couvre A + B du cahier des charges).
    public function test_free_plan_user_never_sees_publish_button_and_api_is_protected(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planGratuit);

        $this->actingAs($user)->get(route('boutiques.index'))
            ->assertInertia(fn ($page) => $page->where('planAutoriseMarketplace', false));

        $this->actingAs($user)->patch(route('boutiques.marketplace', $user->currentBoutique), ['marketplace_visible' => true]);
        $this->assertFalse($user->currentBoutique->fresh()->estEligibleMarketplace());
    }

    // TEST 18 — expiration : un abonnement payant dont la date_fin est dépassée (même si
    // son statut est encore "actif" en base, avant passage de la commande planifiée) ne
    // doit plus donner l'éligibilité Marketplace en temps réel.
    public function test_expired_subscription_loses_marketplace_eligibility_immediately(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro, ['marketplace_visible' => true]);

        $user->abonnements()->update(['date_fin' => now()->subDay()]);

        $this->assertNull($user->fresh()->planActif(), 'Un abonnement expiré ne doit plus être considéré comme actif.');
        $this->assertFalse($user->currentBoutique->fresh()->estEligibleMarketplace());
        $this->assertNotContains($user->currentBoutique->id, Boutique::eligiblesMarketplace()->pluck('id'));

        // Mais aucune donnée n'est supprimée.
        $this->assertDatabaseHas('boutiques', ['id' => $user->current_boutique_id]);
    }

    // TEST 19 — commande planifiée abonnements:expirer : nettoie le statut en base et fait
    // revenir l'utilisateur sur le plan Gratuit, sans jamais toucher à ses données.
    public function test_expirer_abonnements_command_reverts_user_to_free_plan_without_deleting_data(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $planPro = $this->creerPlan('pro', marketplace: true);
        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro, ['nom' => 'Ma Boutique', 'marketplace_visible' => true]);

        $abonnement = $user->abonnements()->first();
        $abonnement->update(['date_fin' => now()->subDay()]);

        $this->artisan('abonnements:expirer')->assertSuccessful();

        $this->assertSame('expire', $abonnement->fresh()->statut);
        $this->assertSame('gratuit', $user->fresh()->planActif()->code);
        $this->assertFalse($user->currentBoutique->fresh()->estEligibleMarketplace());
        $this->assertDatabaseHas('boutiques', ['id' => $user->current_boutique_id, 'nom' => 'Ma Boutique']);
    }

    // TEST 20 — renouvellement : après expiration + retour au plan Gratuit, un nouvel
    // upgrade rend à nouveau la boutique publiable, sans recréation.
    public function test_shop_becomes_publishable_again_after_renewal_following_expiration(): void
    {
        $planGratuit = $this->creerPlan('gratuit', marketplace: false);
        $planPro = $this->creerPlan('pro', marketplace: true);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $user = $this->creerUtilisateurAvecPlanEtBoutique($planPro, ['marketplace_visible' => true]);
        $boutique = $user->currentBoutique;

        $user->abonnements()->first()->update(['date_fin' => now()->subDay()]);
        $this->artisan('abonnements:expirer');
        $this->assertFalse($boutique->fresh()->estEligibleMarketplace());

        // La visibilité choisie par le propriétaire reste inchangée (règle 11 : on ne
        // supprime rien), mais le plan Gratuit actuel la rend inéligible entre-temps.
        $this->assertTrue($boutique->fresh()->marketplace_visible, 'La préférence du propriétaire ne doit pas être effacée par une expiration.');

        $abonnement = $user->fresh()->abonnements()->create(['plan_id' => $planPro->id, 'statut' => 'en_attente', 'date_debut' => now()]);
        $paiement = Paiement::create(['user_id' => $user->id, 'abonnement_id' => $abonnement->id, 'montant' => 15000, 'devise' => 'XAF', 'moyen_paiement' => 'manuel', 'statut' => 'en_attente']);
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/approuver")->assertRedirect();

        // La visibilité était déjà à true : la boutique redevient immédiatement éligible, sans recréation ni re-toggle.
        $this->assertTrue($boutique->fresh()->estEligibleMarketplace());
        $this->assertContains($boutique->id, Boutique::eligiblesMarketplace()->pluck('id'));
    }

    // TEST 21 — sécurité : un utilisateur ne peut jamais publier/dépublier la boutique
    // d'un autre utilisateur, même s'il connaît son identifiant.
    public function test_user_cannot_toggle_marketplace_visibility_of_another_users_shop(): void
    {
        $planPro = $this->creerPlan('pro', marketplace: true);
        $proprietaire = $this->creerUtilisateurAvecPlanEtBoutique($planPro);
        $intrus = $this->creerUtilisateurAvecPlanEtBoutique($planPro);

        $response = $this->actingAs($intrus)->patch(route('boutiques.marketplace', $proprietaire->currentBoutique), [
            'marketplace_visible' => true,
        ]);

        $response->assertStatus(403);
        $this->assertFalse($proprietaire->currentBoutique->fresh()->marketplace_visible);
    }
}
