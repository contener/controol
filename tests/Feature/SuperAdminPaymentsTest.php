<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\PaiementAudit;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function creerPaiementEnAttente(?Plan $plan = null): array
    {
        $plan ??= Plan::create([
            'code' => 'basique', 'nom' => 'Basique', 'prix' => 5000, 'duree_jours' => 30,
        ]);

        $utilisateur = User::factory()->create();
        $abonnement = $utilisateur->abonnements()->create([
            'plan_id' => $plan->id,
            'statut' => 'en_attente',
            'date_debut' => now(),
        ]);
        $paiement = Paiement::create([
            'user_id' => $utilisateur->id,
            'abonnement_id' => $abonnement->id,
            'montant' => $plan->prix,
            'moyen_paiement' => 'manuel',
            'statut' => 'en_attente',
        ]);

        return [$utilisateur, $paiement, $plan];
    }

    // TEST 1 — Super Admin : accès autorisé.
    public function test_super_admin_can_access_payments_administration(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)
            ->get('/admin/paiements')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Paiements/Index'));
    }

    // TEST 2 — Utilisateur normal : accès refusé, page "Accès refusé" avec retour accueil.
    public function test_normal_user_is_denied_access_and_sees_access_denied_page(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this->actingAs($user)->get('/admin/paiements');

        $response->assertStatus(403);
        $response->assertInertia(fn ($page) => $page->component('Errors/AccesRefuse'));
    }

    // TEST 3 — Admin classique (non super_admin) : accès également refusé.
    public function test_classic_admin_cannot_access_payments_administration(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/paiements')
            ->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login_not_shown_the_page(): void
    {
        $this->get('/admin/paiements')->assertRedirect(route('login'));
    }

    // TEST 4 — Appel direct de l'API/route par un utilisateur normal → 403 JSON, pas de fuite de données.
    public function test_api_style_request_from_normal_user_receives_clean_403_without_data(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this->actingAs($user)
            ->getJson('/admin/paiements');

        $response->assertStatus(403);
        $response->assertJsonMissing(['paiements']);
    }

    // TEST 5 — Super Admin approuve : paiement APPROVED, abonnement activé avec date d'expiration.
    public function test_super_admin_can_approve_payment_and_activate_subscription(): void
    {
        [$utilisateur, $paiement, $plan] = $this->creerPaiementEnAttente();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->post("/admin/paiements/{$paiement->id}/approuver");

        $response->assertRedirect();
        $paiement->refresh();
        $this->assertSame('approuve', $paiement->statut);
        $this->assertSame($superAdmin->id, $paiement->valide_par);
        $this->assertNotNull($paiement->valide_at);

        $abonnement = $paiement->abonnement->fresh();
        $this->assertSame('actif', $abonnement->statut);
        $this->assertNotNull($abonnement->date_fin);
        $this->assertEqualsWithDelta(
            now()->addDays($plan->duree_jours)->timestamp,
            $abonnement->date_fin->timestamp,
            5
        );

        $this->assertDatabaseHas('paiement_audits', [
            'paiement_id' => $paiement->id,
            'admin_id' => $superAdmin->id,
            'action' => 'approbation',
            'statut_avant' => 'en_attente',
            'statut_apres' => 'approuve',
        ]);
    }

    // Le plan ne doit jamais être actif tant que le paiement est en_attente.
    public function test_plan_is_not_activated_while_payment_is_pending(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();

        $this->assertNull($utilisateur->fresh()->planActif());
    }

    public function test_super_admin_can_reject_payment_with_reason(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->post("/admin/paiements/{$paiement->id}/rejeter", [
            'motif' => 'Référence de paiement incorrecte',
        ]);

        $response->assertRedirect();
        $paiement->refresh();
        $this->assertSame('rejete', $paiement->statut);
        $this->assertSame('Référence de paiement incorrecte', $paiement->motif_rejet);
        $this->assertSame('annule', $paiement->abonnement->fresh()->statut);
        $this->assertNull($utilisateur->fresh()->planActif());
    }

    public function test_rejecting_a_payment_requires_a_reason(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->post("/admin/paiements/{$paiement->id}/rejeter", [
            'motif' => '',
        ]);

        $response->assertSessionHasErrors('motif');
        $this->assertSame('en_attente', $paiement->fresh()->statut);
    }

    // TEST 6 — Un utilisateur normal tente d'appeler directement l'action d'approbation.
    public function test_normal_user_cannot_approve_payment_via_direct_api_call(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();
        $attaquant = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this->actingAs($attaquant)->post("/admin/paiements/{$paiement->id}/approuver");

        $response->assertStatus(403);
        $this->assertSame('en_attente', $paiement->fresh()->statut);
        $this->assertNull($paiement->fresh()->valide_par);
        $this->assertNull($utilisateur->fresh()->planActif());
    }

    public function test_normal_user_cannot_reject_payment_via_direct_api_call(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();
        $attaquant = User::factory()->create(['role' => User::ROLE_USER]);

        $response = $this->actingAs($attaquant)->post("/admin/paiements/{$paiement->id}/rejeter", [
            'motif' => 'peu importe',
        ]);

        $response->assertStatus(403);
        $this->assertSame('en_attente', $paiement->fresh()->statut);
    }

    // Un utilisateur ne peut jamais s'auto-promouvoir via le formulaire de profil.
    public function test_user_cannot_self_promote_role_via_profile_update(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);
        $this->actingAs($user);

        $this->put('/user/profile-information', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'super_admin',
        ]);

        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_already_processed_payment_cannot_be_processed_again(): void
    {
        [$utilisateur, $paiement] = $this->creerPaiementEnAttente();
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $paiement->update(['statut' => 'approuve', 'valide_par' => $superAdmin->id, 'valide_at' => now()]);

        $response = $this->actingAs($superAdmin)->post("/admin/paiements/{$paiement->id}/rejeter", [
            'motif' => 'trop tard',
        ]);

        $response->assertSessionHas('flash_error');
        $this->assertSame('approuve', $paiement->fresh()->statut);
    }
}
