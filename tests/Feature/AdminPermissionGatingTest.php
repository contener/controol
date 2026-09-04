<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionGatingTest extends TestCase
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

    private function creerPaiementEnAttente(): array
    {
        $plan = Plan::firstOrCreate(['code' => 'basique'], ['nom' => 'Basique', 'prix' => 5000, 'duree_jours' => 30]);
        $utilisateur = User::factory()->create();
        $abonnement = $utilisateur->abonnements()->create(['plan_id' => $plan->id, 'statut' => 'en_attente', 'date_debut' => now()]);
        $paiement = Paiement::create([
            'user_id' => $utilisateur->id, 'abonnement_id' => $abonnement->id,
            'montant' => $plan->prix, 'moyen_paiement' => 'manuel', 'statut' => 'en_attente',
        ]);

        return [$utilisateur, $paiement];
    }

    // TEST 1 — un admin ADMIN_PAIEMENTS peut voir/valider/refuser des paiements.
    public function test_paiements_admin_can_view_and_approve_reject_payments(): void
    {
        $admin = $this->creerAdminAvecPermissions(['paiements.voir', 'paiements.valider', 'paiements.refuser']);

        $this->actingAs($admin)->get('/admin/paiements')->assertOk();

        [, $paiement1] = $this->creerPaiementEnAttente();
        $this->actingAs($admin)->post("/admin/paiements/{$paiement1->id}/approuver")->assertRedirect();
        $this->assertSame('approuve', $paiement1->fresh()->statut);

        [, $paiement2] = $this->creerPaiementEnAttente();
        $this->actingAs($admin)->post("/admin/paiements/{$paiement2->id}/rejeter", ['motif' => 'Justificatif illisible'])->assertRedirect();
        $this->assertSame('rejete', $paiement2->fresh()->statut);
    }

    // TEST 2 — le même admin reçoit 403 sur Marketplace et sur Administrateurs.
    public function test_paiements_only_admin_is_blocked_from_marketplace_and_administrateurs(): void
    {
        $admin = $this->creerAdminAvecPermissions(['paiements.voir', 'paiements.valider', 'paiements.refuser']);

        $this->actingAs($admin)->get('/admin/marketplace')->assertStatus(403);
        $this->actingAs($admin)->get('/admin/administrateurs')->assertStatus(403);
    }

    // TEST 3 — désactivation : blocage immédiat et total sur une action pourtant permise
    // juste avant. Depuis EnsureAccountActive (compte désactivé = déconnexion immédiate,
    // quelle que soit la route), l'utilisateur n'atteint même plus le contrôle de
    // permission : il est redirigé vers /login avant de pouvoir agir.
    public function test_deactivating_admin_immediately_blocks_previously_allowed_action(): void
    {
        $admin = $this->creerAdminAvecPermissions(['paiements.voir', 'paiements.valider']);
        [, $paiement] = $this->creerPaiementEnAttente();

        $this->actingAs($admin)->get('/admin/paiements')->assertOk();

        $admin->forceFill(['est_actif' => false])->save();

        $this->actingAs($admin)->get('/admin/paiements')->assertRedirect(route('login'));
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/approuver")->assertRedirect(route('login'));
        $this->assertSame('en_attente', $paiement->fresh()->statut, 'Le paiement ne doit pas avoir été traité.');
    }

    // TEST 4 — ajout d'une permission en base entre deux requêtes de la même session
    // admin (identité inchangée, mais objet User rechargé comme le ferait une vraie
    // nouvelle requête HTTP en production) : le deuxième appel réussit immédiatement,
    // preuve d'absence de cache applicatif sur les permissions — seule la mise en cache
    // de relation Eloquent PAR INSTANCE PHP (jamais entre deux requêtes réelles) exige
    // ici un ->fresh(), exactement ce qu'une vraie requête ferait naturellement.
    public function test_newly_granted_permission_takes_effect_without_relogin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/marketplace')->assertStatus(403);

        AdminPermission::create(['user_id' => $admin->id, 'permission' => 'marketplace.voir']);

        $this->actingAs($admin->fresh())->get('/admin/marketplace')->assertOk();
    }

    // TEST 5 — requête API sans permission → 403 propre, aucune fuite de données.
    public function test_api_style_request_from_admin_without_permission_receives_clean_403(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [, $paiement] = $this->creerPaiementEnAttente();

        $response = $this->actingAs($admin)->getJson('/admin/paiements');

        $response->assertStatus(403);
        $response->assertJsonMissing(['paiements']);
    }

    // TEST 6 — même une clé de permission qui n'existe dans aucun catalogue, insérée
    // directement en base (contournement de la validation), ne donne jamais accès à
    // /admin/administrateurs/* : cette section ne vérifie QUE isSuperAdmin(), jamais une
    // permission — il n'existe structurellement aucune clé "administrateurs.gerer".
    public function test_no_permission_key_can_unlock_administrateurs_section(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AdminPermission::create(['user_id' => $admin->id, 'permission' => 'administrateurs.gerer']);

        $this->actingAs($admin)->get('/admin/administrateurs')->assertStatus(403);
    }

    // TEST 7 — le Super Admin continue de tout traverser malgré la restructuration des
    // routes (aucune régression), sans qu'aucune ligne admin_permissions n'existe pour lui.
    public function test_super_admin_bypasses_all_granular_permission_checks(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->get('/admin/paiements')->assertOk();
        $this->actingAs($superAdmin)->get('/admin/marketplace')->assertOk();
        $this->actingAs($superAdmin)->get('/admin/administrateurs')->assertOk();
        $this->actingAs($superAdmin)->get('/admin')->assertOk();
    }
}
