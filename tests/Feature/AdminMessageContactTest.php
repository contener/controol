<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\NotificationUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMessageContactTest extends TestCase
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

    public function test_admin_without_notifications_envoyer_permission_is_forbidden(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir']);
        $utilisateur = User::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/utilisateurs/{$utilisateur->id}/message", ['message' => 'Bonjour']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('notifications_utilisateurs', 0);
    }

    public function test_authorized_admin_can_send_an_internal_message_and_a_bell_notification_is_created(): void
    {
        $admin = $this->creerAdminAvecPermissions(['notifications.envoyer']);
        $utilisateur = User::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/utilisateurs/{$utilisateur->id}/message", [
            'message' => 'Votre essai se termine bientôt, pensez à vous abonner !',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications_utilisateurs', [
            'user_id' => $utilisateur->id,
            'message' => 'Votre essai se termine bientôt, pensez à vous abonner !',
        ]);
        $notification = NotificationUtilisateur::where('user_id', $utilisateur->id)->firstOrFail();
        $this->assertStringContainsString('équipe technique', $notification->titre);
        $this->assertTrue($notification->est_promotionnelle);
        $this->assertNull($notification->lu_a);
    }

    /**
     * Le canal WhatsApp disparaît/échoue pour toute personne sans numéro renseigné --
     * le canal message interne, lui, ne dépend d'aucun numéro et doit donc toujours
     * fonctionner, contrairement à whatsappContacter().
     */
    public function test_message_relaunch_works_even_without_a_whatsapp_number(): void
    {
        $admin = $this->creerAdminAvecPermissions(['notifications.envoyer']);
        $utilisateur = User::factory()->create(['whatsapp' => null]);

        $response = $this->actingAs($admin)->post("/admin/utilisateurs/{$utilisateur->id}/message", ['message' => 'Bonjour']);

        $response->assertRedirect();
        $this->assertDatabaseCount('notifications_utilisateurs', 1);
    }

    public function test_regular_user_is_forbidden_from_the_message_relance_route(): void
    {
        $utilisateur = User::factory()->create();
        $autreUtilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->post("/admin/utilisateurs/{$autreUtilisateur->id}/message", ['message' => 'Bonjour'])->assertStatus(403);
    }

    public function test_an_admin_account_cannot_be_targeted_by_the_message_relance_route(): void
    {
        $admin = $this->creerAdminAvecPermissions(['notifications.envoyer']);
        $autreAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post("/admin/utilisateurs/{$autreAdmin->id}/message", ['message' => 'Bonjour'])->assertStatus(404);
    }

    public function test_admin_can_send_a_bulk_message_with_each_recipients_own_name_substituted(): void
    {
        $admin = $this->creerAdminAvecPermissions(['notifications.envoyer']);
        $jean = User::factory()->create(['name' => 'Jean']);
        $marie = User::factory()->create(['name' => 'Marie']);

        $response = $this->actingAs($admin)->post('/admin/utilisateurs/relance-massive', [
            'message' => 'Bonjour {{nom}}, votre essai se termine bientôt.',
            'utilisateur_ids' => [$jean->id, $marie->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('notifications_utilisateurs', [
            'user_id' => $jean->id,
            'message' => 'Bonjour Jean, votre essai se termine bientôt.',
        ]);
        $this->assertDatabaseHas('notifications_utilisateurs', [
            'user_id' => $marie->id,
            'message' => 'Bonjour Marie, votre essai se termine bientôt.',
        ]);
        $this->assertDatabaseCount('notifications_utilisateurs', 2);
    }

    public function test_bulk_message_ignores_admin_accounts_mixed_into_the_selection(): void
    {
        $admin = $this->creerAdminAvecPermissions(['notifications.envoyer']);
        $utilisateur = User::factory()->create();
        $autreAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post('/admin/utilisateurs/relance-massive', [
            'message' => 'Bonjour {{nom}}',
            'utilisateur_ids' => [$utilisateur->id, $autreAdmin->id],
        ])->assertRedirect();

        $this->assertDatabaseCount('notifications_utilisateurs', 1);
        $this->assertDatabaseHas('notifications_utilisateurs', ['user_id' => $utilisateur->id]);
    }

    public function test_bulk_message_requires_at_least_one_recipient(): void
    {
        $admin = $this->creerAdminAvecPermissions(['notifications.envoyer']);

        $this->actingAs($admin)->post('/admin/utilisateurs/relance-massive', [
            'message' => 'Bonjour',
            'utilisateur_ids' => [],
        ])->assertInvalid('utilisateur_ids');
    }

    public function test_bulk_message_without_permission_is_forbidden(): void
    {
        $admin = $this->creerAdminAvecPermissions(['utilisateurs.voir']);
        $utilisateur = User::factory()->create();

        $response = $this->actingAs($admin)->post('/admin/utilisateurs/relance-massive', [
            'message' => 'Bonjour {{nom}}',
            'utilisateur_ids' => [$utilisateur->id],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('notifications_utilisateurs', 0);
    }
}
