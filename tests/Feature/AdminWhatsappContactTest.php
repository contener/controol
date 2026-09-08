<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\User;
use App\Models\WhatsappContactLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWhatsappContactTest extends TestCase
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

    public function test_admin_with_voir_permission_sees_the_whatsapp_number_in_the_list_and_show(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir', 'whatsapp.voir']);
        $utilisateur = User::factory()->create(['whatsapp' => '+237690000000']);

        $index = $this->actingAs($admin)->get('/admin/utilisateurs');
        $index->assertInertia(fn ($page) => $page->where('utilisateurs.data.0.whatsapp', '+237690000000'));

        $show = $this->actingAs($admin)->get("/admin/utilisateurs/{$utilisateur->id}");
        $show->assertInertia(fn ($page) => $page->where('utilisateur.whatsapp', '+237690000000'));
    }

    public function test_admin_without_voir_permission_never_receives_the_raw_number(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir']);
        $utilisateur = User::factory()->create(['whatsapp' => '+237690000000']);

        $index = $this->actingAs($admin)->get('/admin/utilisateurs');
        $index->assertInertia(fn ($page) => $page->where('utilisateurs.data.0.whatsapp', null));

        $show = $this->actingAs($admin)->get("/admin/utilisateurs/{$utilisateur->id}");
        $show->assertInertia(fn ($page) => $page->where('utilisateur.whatsapp', null));
    }

    public function test_admin_without_contacter_permission_is_forbidden_from_the_relance_route(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir']);
        $utilisateur = User::factory()->create(['whatsapp' => '+237690000000']);

        $response = $this->actingAs($admin)->postJson("/admin/utilisateurs/{$utilisateur->id}/whatsapp", ['message' => 'Bonjour']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('whatsapp_contact_logs', 0);
    }

    public function test_authorized_admin_can_open_whatsapp_and_a_log_is_created(): void
    {
        $admin = $this->creerAdminAvecPermissions(['whatsapp.contacter']);
        $utilisateur = User::factory()->create(['whatsapp' => '+237 690 00 00 00']);

        $response = $this->actingAs($admin)->postJson("/admin/utilisateurs/{$utilisateur->id}/whatsapp", [
            'message' => "Bonjour, comment ça va ?\nVotre abonnement expire bientôt.",
        ]);

        $response->assertOk();
        $lien = $response->json('lien');
        $this->assertStringStartsWith('https://wa.me/237690000000?text=', $lien);
        $this->assertStringContainsString(rawurlencode("Bonjour, comment ça va ?\nVotre abonnement expire bientôt."), $lien);

        $this->assertDatabaseHas('whatsapp_contact_logs', [
            'user_id' => $utilisateur->id,
            'admin_id' => $admin->id,
            'numero_whatsapp' => '+237 690 00 00 00',
        ]);
        $log = WhatsappContactLog::first();
        $this->assertNotNull($log->ouvert_a);
        $this->assertNull($log->confirme_a);
    }

    public function test_relance_on_a_user_without_whatsapp_number_returns_a_clean_error(): void
    {
        $admin = $this->creerAdminAvecPermissions(['whatsapp.contacter']);
        $utilisateur = User::factory()->create(['whatsapp' => null]);

        $response = $this->actingAs($admin)->postJson("/admin/utilisateurs/{$utilisateur->id}/whatsapp", ['message' => 'Bonjour']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('whatsapp_contact_logs', 0);
    }

    public function test_admin_can_confirm_a_relance_was_sent(): void
    {
        $admin = $this->creerAdminAvecPermissions(['whatsapp.contacter']);
        $utilisateur = User::factory()->create(['whatsapp' => '+237690000000']);
        $log = WhatsappContactLog::create([
            'user_id' => $utilisateur->id,
            'admin_id' => $admin->id,
            'numero_whatsapp' => $utilisateur->whatsapp,
            'message' => 'Bonjour',
            'ouvert_a' => now(),
        ]);

        $response = $this->actingAs($admin)->patch("/admin/utilisateurs/whatsapp-logs/{$log->id}/confirmer");

        $response->assertRedirect();
        $this->assertNotNull($log->fresh()->confirme_a);
        $this->assertTrue($log->fresh()->confirme);
    }

    public function test_history_is_only_included_when_historique_permission_is_granted(): void
    {
        $adminSansHistorique = $this->creerAdminAvecPermissions(['utilisateurs.voir', 'whatsapp.voir']);
        $adminAvecHistorique = $this->creerAdminAvecPermissions(['utilisateurs.voir', 'whatsapp.voir', 'whatsapp.historique']);
        $utilisateur = User::factory()->create(['whatsapp' => '+237690000000']);
        WhatsappContactLog::create([
            'user_id' => $utilisateur->id,
            'admin_id' => $adminAvecHistorique->id,
            'numero_whatsapp' => $utilisateur->whatsapp,
            'message' => 'Bonjour',
            'ouvert_a' => now(),
        ]);

        $this->actingAs($adminSansHistorique)->get("/admin/utilisateurs/{$utilisateur->id}")
            ->assertInertia(fn ($page) => $page->has('logsWhatsapp', 0));

        $this->actingAs($adminAvecHistorique)->get("/admin/utilisateurs/{$utilisateur->id}")
            ->assertInertia(fn ($page) => $page->has('logsWhatsapp', 1));
    }

    public function test_search_by_whatsapp_number_finds_the_right_user(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir', 'whatsapp.voir']);
        User::factory()->create(['name' => 'Jean', 'whatsapp' => '+237690000001']);
        User::factory()->create(['name' => 'Marie', 'whatsapp' => '+237699999999']);

        $response = $this->actingAs($admin)->get('/admin/utilisateurs?recherche=690000001');

        $response->assertInertia(fn ($page) => $page->has('utilisateurs.data', 1)
            ->where('utilisateurs.data.0.name', 'Jean'));
    }

    public function test_filter_by_whatsapp_presence_works(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir', 'whatsapp.voir']);
        User::factory()->create(['name' => 'Avec numéro', 'whatsapp' => '+237690000001']);
        User::factory()->create(['name' => 'Sans numéro', 'whatsapp' => null]);

        $avec = $this->actingAs($admin)->get('/admin/utilisateurs?avecWhatsapp=1');
        $avec->assertInertia(fn ($page) => $page->has('utilisateurs.data', 1)
            ->where('utilisateurs.data.0.name', 'Avec numéro'));

        $sans = $this->actingAs($admin)->get('/admin/utilisateurs?avecWhatsapp=0');
        $sans->assertInertia(fn ($page) => $page->has('utilisateurs.data', 1)
            ->where('utilisateurs.data.0.name', 'Sans numéro'));
    }

    public function test_regular_user_is_forbidden_from_all_whatsapp_admin_routes(): void
    {
        $utilisateur = User::factory()->create();
        $autreUtilisateur = User::factory()->create(['whatsapp' => '+237690000000']);

        $this->actingAs($utilisateur)->get('/admin/utilisateurs')->assertStatus(403);
        $this->actingAs($utilisateur)->postJson("/admin/utilisateurs/{$autreUtilisateur->id}/whatsapp", ['message' => 'Bonjour'])->assertStatus(403);
    }
}
