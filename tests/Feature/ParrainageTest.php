<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\CommissionParrainage;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\ReglementParrainage;
use App\Models\User;
use App\Services\ParrainageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class ParrainageTest extends TestCase
{
    use RefreshDatabase;

    private function creerAdminAvecPermissions(array $permissions): User
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ($permissions as $permission) {
            AdminPermission::create(['user_id' => $admin->id, 'permission' => $permission]);
        }

        return $admin;
    }

    private function inscrire(string $email = 'nouveau@example.com', array $overrides = []): User
    {
        $this->post('/register', array_merge([
            'name' => 'Nouveau Compte',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ], $overrides));

        return User::where('email', $email)->firstOrFail();
    }

    private function creerPaiementApprouvePourFilleul(User $filleul, float $montant = 15000): Paiement
    {
        $plan = Plan::firstOrCreate(['code' => 'basique'], ['nom' => 'Basique', 'prix' => $montant, 'duree_jours' => 30]);
        $abonnement = $filleul->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'en_attente', 'date_debut' => now()]);
        $paiement = Paiement::create([
            'user_id' => $filleul->id, 'abonnement_id' => $abonnement->id,
            'montant' => $montant, 'moyen_paiement' => 'manuel', 'statut' => 'en_attente',
        ]);

        $admin = $this->creerAdminAvecPermissions(['paiements.valider']);
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/approuver")->assertRedirect();

        return $paiement->fresh();
    }

    public function test_code_parrainage_is_generated_lazily_and_stable(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->code_parrainage);

        $this->actingAs($user)->get('/compte/parrainage')->assertOk();
        $codeGenere = $user->fresh()->code_parrainage;
        $this->assertNotNull($codeGenere);

        $this->actingAs($user)->get('/compte/parrainage')->assertOk();
        $this->assertSame($codeGenere, $user->fresh()->code_parrainage);
    }

    public function test_capturing_middleware_stores_the_referral_code_in_session(): void
    {
        $parrain = User::factory()->create();
        $code = app(ParrainageService::class)->assurerCodeParrainage($parrain);

        $response = $this->get('/register?ref='.$code);

        $response->assertSessionHas('parrainage_code', $code);
    }

    public function test_unknown_referral_code_is_never_stored_in_session(): void
    {
        $response = $this->get('/register?ref=INEXISTANT');

        $response->assertSessionMissing('parrainage_code');
    }

    public function test_registration_attributes_the_referrer(): void
    {
        $parrain = User::factory()->create();
        $code = app(ParrainageService::class)->assurerCodeParrainage($parrain);

        $response = $this->withSession(['parrainage_code' => $code])->post('/register', [
            'name' => 'Filleul Direct', 'email' => 'filleul-direct@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);
        $response->assertRedirect();

        $nouvelUtilisateur = User::where('email', 'filleul-direct@example.com')->firstOrFail();
        $this->assertSame($parrain->id, $nouvelUtilisateur->parrain_id);
    }

    public function test_registration_without_referral_code_leaves_parrain_null(): void
    {
        $utilisateur = $this->inscrire('organique@example.com');

        $this->assertNull($utilisateur->parrain_id);
    }

    public function test_self_referral_is_blocked(): void
    {
        $utilisateur = User::factory()->create();
        $code = app(ParrainageService::class)->assurerCodeParrainage($utilisateur);

        // Simule une tentative où le code de l'utilisateur pointerait sur lui-même.
        session(['parrainage_code' => $code]);
        app(ParrainageService::class)->apresInscription($utilisateur);

        $this->assertNull($utilisateur->fresh()->parrain_id);
    }

    public function test_parrain_is_never_overwritten_once_attributed(): void
    {
        $parrainInitial = User::factory()->create();
        $autreParrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrainInitial->id]);

        $codeAutre = app(ParrainageService::class)->assurerCodeParrainage($autreParrain);
        session(['parrainage_code' => $codeAutre]);
        app(ParrainageService::class)->apresInscription($filleul);

        $this->assertSame($parrainInitial->id, $filleul->fresh()->parrain_id);
    }

    public function test_pending_payment_creates_no_commission(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);

        $plan = Plan::firstOrCreate(['code' => 'basique'], ['nom' => 'Basique', 'prix' => 15000, 'duree_jours' => 30]);
        $abonnement = $filleul->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'en_attente', 'date_debut' => now()]);
        Paiement::create([
            'user_id' => $filleul->id, 'abonnement_id' => $abonnement->id,
            'montant' => 15000, 'moyen_paiement' => 'manuel', 'statut' => 'en_attente',
        ]);

        $this->assertDatabaseCount('commissions_parrainage', 0);
    }

    public function test_rejected_payment_creates_no_commission(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);

        $plan = Plan::firstOrCreate(['code' => 'basique'], ['nom' => 'Basique', 'prix' => 15000, 'duree_jours' => 30]);
        $abonnement = $filleul->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'en_attente', 'date_debut' => now()]);
        $paiement = Paiement::create([
            'user_id' => $filleul->id, 'abonnement_id' => $abonnement->id,
            'montant' => 15000, 'moyen_paiement' => 'manuel', 'statut' => 'en_attente',
        ]);

        $admin = $this->creerAdminAvecPermissions(['paiements.refuser']);
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/rejeter", ['motif' => 'Justificatif invalide'])->assertRedirect();

        $this->assertDatabaseCount('commissions_parrainage', 0);
    }

    public function test_approved_payment_creates_a_5_percent_commission(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);

        $paiement = $this->creerPaiementApprouvePourFilleul($filleul, 15000);

        $this->assertDatabaseCount('commissions_parrainage', 1);
        $commission = CommissionParrainage::where('paiement_id', $paiement->id)->firstOrFail();
        $this->assertSame($parrain->id, $commission->parrain_id);
        $this->assertSame($filleul->id, $commission->filleul_id);
        $this->assertSame('750.00', (string) $commission->montant_commission);
        $this->assertSame('5.00', (string) $commission->taux);
        $this->assertSame('disponible', $commission->statut);
    }

    public function test_payment_without_a_referrer_creates_no_commission(): void
    {
        $filleul = User::factory()->create();

        $this->creerPaiementApprouvePourFilleul($filleul, 15000);

        $this->assertDatabaseCount('commissions_parrainage', 0);
    }

    public function test_commission_service_is_idempotent_for_the_same_payment(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);
        $paiement = $this->creerPaiementApprouvePourFilleul($filleul, 15000);

        app(ParrainageService::class)->creerCommissionSiEligible($paiement->fresh());
        app(ParrainageService::class)->creerCommissionSiEligible($paiement->fresh());

        $this->assertDatabaseCount('commissions_parrainage', 1);
    }

    public function test_statistics_are_accurate(): void
    {
        $parrain = User::factory()->create();

        $filleulValide = User::factory()->create(['parrain_id' => $parrain->id]);
        $this->creerPaiementApprouvePourFilleul($filleulValide, 10000);

        $filleulEnAttente = User::factory()->create(['parrain_id' => $parrain->id]);
        $planEnAttente = Plan::firstOrCreate(['code' => 'basique'], ['nom' => 'Basique', 'prix' => 10000, 'duree_jours' => 30]);
        $abonnementEnAttente = $filleulEnAttente->abonnements()->create(['plan_id' => $planEnAttente->id, 'statut' => 'en_attente', 'date_debut' => now()]);
        Paiement::create(['user_id' => $filleulEnAttente->id, 'abonnement_id' => $abonnementEnAttente->id, 'montant' => 10000, 'moyen_paiement' => 'manuel', 'statut' => 'en_attente']);

        User::factory()->create(['parrain_id' => $parrain->id]); // aucun paiement du tout

        $stats = app(ParrainageService::class)->statistiques($parrain->fresh());

        $this->assertSame(3, $stats['comptes_crees']);
        $this->assertSame(2, $stats['paiements_commences']);
        $this->assertSame(1, $stats['paiements_valides']);
        $this->assertSame(500.0, $stats['gains_generes']);
        $this->assertSame(500.0, $stats['solde_disponible']);
    }

    public function test_referral_list_never_exposes_filleul_email(): void
    {
        $parrain = User::factory()->create();
        User::factory()->create(['parrain_id' => $parrain->id, 'name' => 'Filleul Discret', 'email' => 'secret@example.com']);

        $response = $this->actingAs($parrain)->get('/compte/parrainage');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Compte/Parrainage')
            ->where('filleuls.0.nom', 'Filleul Discret')
            ->missing('filleuls.0.email'));
    }

    public function test_admin_without_permission_is_blocked_from_parrainage_section(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/parrainage')->assertStatus(403);
    }

    public function test_admin_with_permission_can_view_parrainage_section(): void
    {
        $admin = $this->creerAdminAvecPermissions(['parrainage.voir']);

        $this->actingAs($admin)->get('/admin/parrainage')->assertOk();
    }

    public function test_super_admin_bypasses_permission_check(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->get('/admin/parrainage')->assertOk();
    }

    public function test_payout_reduces_balance_without_deleting_commission_history(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);
        $this->creerPaiementApprouvePourFilleul($filleul, 10000); // commission = 500

        $admin = $this->creerAdminAvecPermissions(['parrainage.gerer']);
        $this->actingAs($admin)->post("/admin/parrainage/{$parrain->id}/reglements", [
            'montant' => 500,
            'methode_paiement' => 'Mobile Money',
        ])->assertRedirect();

        $this->assertDatabaseCount('commissions_parrainage', 1);
        $this->assertDatabaseCount('reglements_parrainage', 1);
        $this->assertSame(0.0, app(ParrainageService::class)->soldeDisponible($parrain->fresh()));
    }

    public function test_partial_payout_leaves_the_remaining_balance_correct(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);
        $this->creerPaiementApprouvePourFilleul($filleul, 20000); // commission = 1000

        $admin = $this->creerAdminAvecPermissions(['parrainage.gerer']);
        $this->actingAs($admin)->post("/admin/parrainage/{$parrain->id}/reglements", ['montant' => 600])->assertRedirect();

        $this->assertSame(400.0, app(ParrainageService::class)->soldeDisponible($parrain->fresh()));
    }

    public function test_payout_exceeding_available_balance_is_rejected(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);
        $this->creerPaiementApprouvePourFilleul($filleul, 10000); // commission = 500

        $admin = $this->creerAdminAvecPermissions(['parrainage.gerer']);
        $this->actingAs($admin)->post("/admin/parrainage/{$parrain->id}/reglements", ['montant' => 5000])
            ->assertStatus(422);

        $this->assertDatabaseCount('reglements_parrainage', 0);
    }

    public function test_admin_without_gerer_permission_cannot_record_a_payout(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);
        $this->creerPaiementApprouvePourFilleul($filleul, 10000);

        $admin = $this->creerAdminAvecPermissions(['parrainage.voir']);
        $this->actingAs($admin)->post("/admin/parrainage/{$parrain->id}/reglements", ['montant' => 100])
            ->assertStatus(403);
    }

    public function test_admin_can_cancel_a_commission_and_it_is_excluded_from_totals_but_kept(): void
    {
        $parrain = User::factory()->create();
        $filleul = User::factory()->create(['parrain_id' => $parrain->id]);
        $paiement = $this->creerPaiementApprouvePourFilleul($filleul, 10000);
        $commission = CommissionParrainage::where('paiement_id', $paiement->id)->firstOrFail();

        $admin = $this->creerAdminAvecPermissions(['parrainage.gerer']);
        $this->actingAs($admin)->post("/admin/parrainage/commissions/{$commission->id}/annuler", [
            'motif' => 'Paiement remboursé au client',
        ])->assertRedirect();

        $commission->refresh();
        $this->assertSame('annulee', $commission->statut);
        $this->assertSame($admin->id, $commission->annule_par);
        $this->assertNotNull($commission->annule_at);
        $this->assertSame(0.0, app(ParrainageService::class)->soldeDisponible($parrain->fresh()));

        // La ligne n'est jamais supprimée -- historique conservé.
        $this->assertDatabaseHas('commissions_parrainage', ['id' => $commission->id]);
    }
}
