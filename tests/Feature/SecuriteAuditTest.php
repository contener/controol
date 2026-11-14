<?php

namespace Tests\Feature;

use App\Models\AudienceInteraction;
use App\Models\CommissionParrainage;
use App\Models\Conversation;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Jetstream;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Corrections de l'audit de sécurité du 2026-11-14 (phase 2, analyse statique) --
 * un test par vulnérabilité, qui échouait avant correction.
 */
class SecuriteAuditTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function jetonPour(User $user): string
    {
        return $user->createToken('Test')->plainTextToken;
    }

    /** V-01 : un compte désactivé perd immédiatement l'accès à l'API. */
    public function test_v01_deactivated_account_loses_api_access_immediately(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $jeton = $this->jetonPour($user);

        $this->withToken($jeton)->getJson('/api/clients')->assertOk();

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->patch(route('admin.utilisateurs.basculer', $user))->assertRedirect();

        $this->assertSame(0, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($jeton)->getJson('/api/clients')->assertUnauthorized();
    }

    /** V-01 : même sans passer par l'écran admin (ex. désactivation en base), le jeton est refusé. */
    public function test_v01_inactive_account_token_is_rejected_by_middleware(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $jeton = $this->jetonPour($user);
        $user->forceFill(['est_actif' => false])->save();

        $this->withToken($jeton)->getJson('/api/dashboard')->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    /** V-02 : changer son mot de passe depuis l'app déconnecte les autres appareils. */
    public function test_v02_password_change_revokes_other_tokens(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->forceFill(['password' => Hash::make('ancien-mot-de-passe')])->save();
        $jetonVole = $this->jetonPour($user);
        $jetonCourant = $this->jetonPour($user);

        $this->withToken($jetonCourant)->putJson('/api/compte/mot-de-passe', [
            'current_password' => 'ancien-mot-de-passe',
            'password' => 'NouveauMotDePasse123!',
            'password_confirmation' => 'NouveauMotDePasse123!',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($jetonVole)->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($jetonCourant)->getJson('/api/user')->assertOk();
    }

    /** V-02 : réinitialiser son mot de passe révoque tous les jetons mobiles. */
    public function test_v02_password_reset_revokes_all_tokens(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $this->jetonPour($user);

        app(\Laravel\Fortify\Contracts\ResetsUserPasswords::class)->reset($user, [
            'password' => 'NouveauMotDePasse123!',
            'password_confirmation' => 'NouveauMotDePasse123!',
        ]);

        $this->assertSame(0, $user->tokens()->count());
    }

    /** V-02 : les jetons expirent (30 jours par défaut). */
    public function test_v02_tokens_expire(): void
    {
        $this->assertSame(60 * 24 * 30, config('sanctum.expiration'));

        $user = $this->creerUtilisateurAvecBoutique('pro');
        $jeton = $this->jetonPour($user);
        $this->travel(31)->days();

        $this->withToken($jeton)->getJson('/api/user')->assertUnauthorized();
    }

    /** V-03 : la page publique et la Marketplace mobile ne révèlent plus le prix d'achat ni le stock. */
    public function test_v03_public_shop_does_not_expose_purchase_price_or_stock(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique('pro');
        $boutique = $vendeur->currentBoutique;
        Produit::withoutGlobalScopes()->create([
            'boutique_id' => $boutique->id, 'type' => 'produit', 'nom' => 'Sac', 'reference' => 'REF-SECRETE',
            'prix_achat' => 1234, 'prix_vente' => 5000, 'unite' => 'pièce', 'gere_stock' => true,
            'quantite_stock' => 77, 'seuil_alerte' => 3, 'actif' => true, 'marketplace_visible' => true,
        ]);

        $this->get(route('public.boutique', $boutique->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('produits.0.nom', 'Sac')
                ->missing('produits.0.prix_achat')
                ->missing('produits.0.quantite_stock')
                ->missing('produits.0.reference')
                ->missing('produits.0.seuil_alerte'));

        $visiteur = $this->creerUtilisateurAvecBoutique('gratuit');
        $json = $this->actingAs($visiteur, 'sanctum')->getJson("/api/marketplace/boutiques/{$boutique->slug}")->assertOk()->json('produits.0');
        $this->assertSame('Sac', $json['nom']);
        $this->assertArrayNotHasKey('prix_achat', $json);
        $this->assertArrayNotHasKey('quantite_stock', $json);
        $this->assertArrayNotHasKey('reference', $json);
    }

    /** V-04 : un visiteur bloqué ne peut plus répondre depuis "Mes conversations" (web et API). */
    public function test_v04_blocked_visitor_cannot_reply_from_my_conversations(): void
    {
        $vendeur = $this->creerUtilisateurAvecBoutique('pro');
        $visiteur = $this->creerUtilisateurAvecBoutique('gratuit');
        $conversation = Conversation::withoutGlobalScopes()->create([
            'boutique_id' => $vendeur->current_boutique_id,
            'visiteur_user_id' => $visiteur->id,
            'visiteur_nom' => 'Harceleur',
            'statut' => Conversation::STATUT_BLOQUEE,
        ]);

        $this->actingAs($visiteur, 'sanctum')
            ->postJson("/api/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Encore moi'])
            ->assertForbidden();
        $this->actingAs($visiteur)
            ->post(route('mes-conversations.repondre', $conversation->id), ['contenu' => 'Encore moi'])
            ->assertForbidden();

        $this->assertSame(0, $conversation->messages()->count());
    }

    private function paiementEnAttente(User $user): Paiement
    {
        $plan = Plan::firstOrCreate(['code' => 'basique'], ['nom' => 'Basique', 'prix' => 5000, 'duree_jours' => 30]);
        $abonnement = $user->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'en_attente', 'date_debut' => now()]);

        return Paiement::create([
            'user_id' => $user->id, 'abonnement_id' => $abonnement->id, 'montant' => 5000,
            'moyen_paiement' => 'manuel', 'statut' => 'en_attente',
        ]);
    }

    /** V-05 : un administrateur ne peut ni valider ni rejeter son propre paiement. */
    public function test_v05_admin_cannot_process_own_payment(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $paiement = $this->paiementEnAttente($superAdmin);

        $this->actingAs($superAdmin)->post(route('admin.paiements.approuver', $paiement))->assertSessionHas('flash_error');
        $this->assertSame('en_attente', $paiement->fresh()->statut);

        $this->actingAs($superAdmin)->post(route('admin.paiements.rejeter', $paiement), ['motif' => 'test'])->assertSessionHas('flash_error');
        $this->assertSame('en_attente', $paiement->fresh()->statut);
    }

    /** V-06 : inscriptions limitées (10/heure par IP), web et API. */
    public function test_v06_registrations_are_rate_limited(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->postJson('/api/register', [
                'name' => "Compte {$i}", 'email' => "compte{$i}@example.com",
                'password' => 'mot-de-passe-solide', 'password_confirmation' => 'mot-de-passe-solide',
                'boutique' => ['nom' => "Boutique {$i}"],
            ])->assertCreated();
        }

        $this->postJson('/api/register', [
            'name' => 'Compte 11', 'email' => 'compte11@example.com',
            'password' => 'mot-de-passe-solide', 'password_confirmation' => 'mot-de-passe-solide',
            'boutique' => ['nom' => 'Boutique 11'],
        ])->assertStatus(429);

        // Le limiteur est commun au web : le formulaire est lui aussi bloqué.
        $this->post('/register', [
            'name' => 'Web', 'email' => 'web@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(), 'boutique' => ['nom' => 'Web'],
        ])->assertStatus(429);
    }

    /** V-08 : une seconde validation (concurrente ou rejouée) ne retraite jamais le paiement. */
    public function test_v08_service_refuses_to_process_an_already_processed_payment(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $paiement = $this->paiementEnAttente(User::factory()->create());
        $service = app(\App\Services\PaiementValidationService::class);

        $this->assertNotNull($service->approuver($paiement, $superAdmin, request()));
        // Même instance "périmée" (statut en_attente en mémoire), comme lors d'un double clic.
        $this->assertNull($service->approuver($paiement, $superAdmin, request()));
        $this->assertNull($service->rejeter($paiement, $superAdmin, 'trop tard', request()));
        $this->assertSame(Paiement::STATUT_APPROUVE, $paiement->fresh()->statut);
    }

    /** V-09 : pas de commission quand parrain et filleul partagent le même numéro. */
    public function test_v09_no_commission_for_self_referral(): void
    {
        $parrain = User::factory()->create(['telephone' => '+237 6 90 11 22 33']);
        $filleul = User::factory()->create(['whatsapp' => '690112233']);
        $filleul->forceFill(['parrain_id' => $parrain->id])->save();

        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        app(\App\Services\PaiementValidationService::class)->approuver($this->paiementEnAttente($filleul), $superAdmin, request());

        $this->assertSame(0, CommissionParrainage::count());
    }

    /** PV-5 : un plan à prix 0 autre que "gratuit" n'est pas activable par un utilisateur. */
    public function test_pv5_free_internal_plan_cannot_be_self_activated(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $planOffert = Plan::create(['code' => 'partenaire', 'nom' => 'Partenaire', 'prix' => 0, 'devise' => 'XAF']);

        $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$planOffert->id}")->assertForbidden();
        $this->assertFalse($user->abonnements()->where('plan_id', $planOffert->id)->exists());
    }

    /** PV-6 : redemander le même plan réutilise la demande en attente. */
    public function test_pv6_repeated_plan_requests_reuse_the_pending_payment(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $plan = Plan::create(['code' => 'basique', 'nom' => 'Basique', 'prix' => 5000, 'devise' => 'XAF']);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$plan->id}")->assertOk();
        }

        $this->assertSame(1, Paiement::where('user_id', $user->id)->count());
    }

    /** Défaut fonctionnel relevé pendant l'audit : un collaborateur peut basculer sur sa boutique. */
    public function test_collaborator_can_switch_to_the_shop_they_work_in_but_not_others(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $boutique = $proprietaire->currentBoutique;
        $collaborateur = $this->creerUtilisateurAvecBoutique('gratuit');
        $boutique->collaborateurs()->attach($collaborateur->id);

        $this->actingAs($collaborateur, 'sanctum')->postJson("/api/boutiques/{$boutique->id}/switch")->assertOk();
        $this->assertSame($boutique->id, $collaborateur->fresh()->current_boutique_id);

        $etrangere = $this->creerUtilisateurAvecBoutique('pro')->currentBoutique;
        $this->actingAs($collaborateur, 'sanctum')->postJson("/api/boutiques/{$etrangere->id}/switch")->assertForbidden();
    }
}
