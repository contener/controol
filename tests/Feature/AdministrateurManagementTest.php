<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrateurManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payloadAdministrateur(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
            'telephone' => '+237600000000',
            'password' => 'MotDePasseTemporaire123!',
            'admin_role_label' => 'ADMIN_PAIEMENTS',
            'permissions' => ['paiements.voir', 'paiements.valider', 'paiements.refuser'],
        ], $overrides);
    }

    // TEST 1 — Super Admin crée un administrateur avec étiquette de rôle + permissions.
    public function test_super_admin_can_create_administrateur_with_role_label_and_permissions(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->post('/admin/administrateurs', $this->payloadAdministrateur());

        $response->assertRedirect(route('admin.administrateurs.index'));

        $administrateur = User::where('email', 'jean.dupont@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $administrateur->role);
        $this->assertSame('ADMIN_PAIEMENTS', $administrateur->admin_role_label);
        $this->assertTrue($administrateur->est_actif);
        $this->assertEqualsCanonicalizing(
            ['paiements.voir', 'paiements.valider', 'paiements.refuser'],
            $administrateur->adminPermissions->pluck('permission')->all(),
        );

        $this->actingAs($superAdmin)->get('/admin/administrateurs/creer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Administrateurs/Create'));

        $this->actingAs($superAdmin)->get("/admin/administrateurs/{$administrateur->id}/modifier")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Administrateurs/Edit')
                ->where('administrateur.email', 'jean.dupont@example.com'));
    }

    // TEST 2 — Liste : recherche et pagination.
    public function test_index_lists_and_searches_administrateurs(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Alice Martin', 'email' => 'alice@example.com']);
        User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Bob Konan', 'email' => 'bob@example.com']);
        User::factory()->create(['role' => User::ROLE_USER]); // ne doit jamais apparaître

        $response = $this->actingAs($superAdmin)->get('/admin/administrateurs?recherche=Alice');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Administrateurs/Index')
            ->has('administrateurs.data', 1)
            ->where('administrateurs.data.0.name', 'Alice Martin'));
    }

    // TEST 3 — Un admin classique (même sans permission) n'accède à AUCUNE route
    // /admin/administrateurs/* : gérer les admins n'est jamais délégable.
    public function test_plain_admin_gets_403_on_every_administrateurs_route(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $autreAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/administrateurs')->assertStatus(403);
        $this->actingAs($admin)->get('/admin/administrateurs/creer')->assertStatus(403);
        $this->actingAs($admin)->post('/admin/administrateurs', $this->payloadAdministrateur())->assertStatus(403);
        $this->actingAs($admin)->get("/admin/administrateurs/{$autreAdmin->id}/modifier")->assertStatus(403);
        $this->actingAs($admin)->put("/admin/administrateurs/{$autreAdmin->id}", $this->payloadAdministrateur())->assertStatus(403);
        $this->actingAs($admin)->patch("/admin/administrateurs/{$autreAdmin->id}/basculer")->assertStatus(403);
    }

    // TEST 4 — role forgé à 'super_admin' : totalement ignoré, le compte créé reste 'admin'.
    public function test_forged_super_admin_role_is_ignored_on_creation(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post('/admin/administrateurs', array_merge(
            $this->payloadAdministrateur(),
            ['role' => 'super_admin'],
        ));

        $administrateur = User::where('email', 'jean.dupont@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $administrateur->role);
        $this->assertFalse($administrateur->isSuperAdmin());
    }

    // TEST 5 — clé de permission inconnue (potentiellement forgée) → 422, rien créé.
    public function test_unknown_permission_key_is_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->postJson('/admin/administrateurs', $this->payloadAdministrateur([
            'permissions' => ['paiements.voir', 'administrateurs.gerer'],
        ]));

        $response->assertStatus(422);
        $response->assertInvalid('permissions.1');
        $this->assertDatabaseMissing('users', ['email' => 'jean.dupont@example.com']);
    }

    // TEST 6 — étiquette de rôle hors catalogue → 422.
    public function test_unknown_role_label_is_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($superAdmin)->postJson('/admin/administrateurs', $this->payloadAdministrateur([
            'admin_role_label' => 'ADMIN_TOUT_PUISSANT',
        ]));

        $response->assertStatus(422);
        $response->assertInvalid('admin_role_label');
    }

    // TEST 7 — création/modification/désactivation écrivent une ligne admin_audits.
    public function test_mutations_write_admin_audit_rows(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post('/admin/administrateurs', $this->payloadAdministrateur());
        $administrateur = User::where('email', 'jean.dupont@example.com')->firstOrFail();

        $this->assertDatabaseHas('admin_audits', [
            'admin_id' => $superAdmin->id,
            'action' => 'administrateur_cree',
            'resource' => 'administrateur',
            'resource_id' => $administrateur->id,
        ]);

        $this->actingAs($superAdmin)->put("/admin/administrateurs/{$administrateur->id}", $this->payloadAdministrateur([
            'permissions' => ['paiements.voir'],
        ]));

        $auditModification = AdminAudit::where('action', 'administrateur_modifie')->where('resource_id', $administrateur->id)->firstOrFail();
        $this->assertEqualsCanonicalizing(['paiements.voir', 'paiements.valider', 'paiements.refuser'], $auditModification->ancienne_valeur['permissions']);
        $this->assertEqualsCanonicalizing(['paiements.voir'], $auditModification->nouvelle_valeur['permissions']);

        $this->actingAs($superAdmin)->patch("/admin/administrateurs/{$administrateur->id}/basculer");
        $this->assertDatabaseHas('admin_audits', [
            'admin_id' => $superAdmin->id,
            'action' => 'administrateur_desactive',
            'resource_id' => $administrateur->id,
        ]);
    }

    // TEST 8 — désactivation : ne supprime ni le compte ni son historique.
    public function test_deactivation_preserves_account_and_history(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdmin)->post('/admin/administrateurs', $this->payloadAdministrateur());
        $administrateur = User::where('email', 'jean.dupont@example.com')->firstOrFail();

        $this->actingAs($superAdmin)->patch("/admin/administrateurs/{$administrateur->id}/basculer");

        $administrateur->refresh();
        $this->assertFalse($administrateur->est_actif);
        $this->assertDatabaseHas('users', ['id' => $administrateur->id]);
        $this->assertDatabaseCount('admin_audits', 2); // création + désactivation
    }

    // TEST 9 — le Super Admin lui-même ne peut jamais être "géré" comme administrateur
    // via cette route (garde assertEstUnAdministrateurGere) : role !== 'admin' → 404.
    public function test_super_admin_cannot_target_a_non_admin_user_via_this_controller(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $autreSuperAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateurNormal = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($superAdmin)->get("/admin/administrateurs/{$autreSuperAdmin->id}/modifier")->assertStatus(404);
        $this->actingAs($superAdmin)->get("/admin/administrateurs/{$utilisateurNormal->id}/modifier")->assertStatus(404);
        $this->actingAs($superAdmin)->patch("/admin/administrateurs/{$utilisateurNormal->id}/basculer")->assertStatus(404);
    }
}
