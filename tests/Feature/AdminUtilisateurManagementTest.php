<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesBoutique;
use Tests\TestCase;

class AdminUtilisateurManagementTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    // TEST 1 — un admin avec la permission utilisateurs.voir liste/recherche les utilisateurs.
    public function test_admin_with_permission_can_list_and_search_users(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->creerUtilisateurAvecBoutique()->forceFill(['name' => 'Alice Martin', 'email' => 'alice@example.com'])->save();

        $response = $this->actingAs($superAdmin)->get('/admin/utilisateurs?recherche=Alice');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Utilisateurs/Index')
            ->has('utilisateurs.data', 1)
            ->where('utilisateurs.data.0.name', 'Alice Martin'));
    }

    // TEST 2 — un admin SANS la permission reçoit 403.
    public function test_admin_without_permission_gets_403_on_utilisateurs_routes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $utilisateur = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($admin)->get('/admin/utilisateurs')->assertStatus(403);
        $this->actingAs($admin)->get("/admin/utilisateurs/{$utilisateur->id}")->assertStatus(403);
    }

    // TEST 3 — la page de détail montre bien "leur contenu" : boutiques + compteurs.
    public function test_show_page_displays_user_boutiques_with_content_counts(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateur = $this->creerUtilisateurAvecBoutique('gratuit', ['nom' => 'Ma Boutique']);
        Client::create(['boutique_id' => $utilisateur->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);

        $response = $this->actingAs($superAdmin)->get("/admin/utilisateurs/{$utilisateur->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Utilisateurs/Show')
            ->where('utilisateur.email', $utilisateur->email)
            ->where('boutiques.0.nom', 'Ma Boutique')
            ->where('boutiques.0.clients_count', 1));
    }

    // TEST 4 — désactivation : est_actif passe à false et l'action est journalisée.
    public function test_deactivating_a_user_updates_status_and_writes_audit(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateur = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($superAdmin)->patch("/admin/utilisateurs/{$utilisateur->id}/basculer")->assertRedirect();

        $this->assertFalse($utilisateur->fresh()->est_actif);
        $this->assertDatabaseHas('admin_audits', [
            'admin_id' => $superAdmin->id,
            'action' => 'utilisateur_desactive',
            'resource' => 'utilisateur',
            'resource_id' => $utilisateur->id,
        ]);
    }

    // Filet EnsureAccountActive : un compte désactivé PENDANT qu'il a une session active
    // en base (SESSION_DRIVER=database) voit cette ligne supprimée par basculerActivation
    // — vérifié directement en base plutôt qu'en simulant deux navigateurs distincts dans
    // un seul client de test.
    public function test_deactivating_a_user_deletes_their_database_sessions(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateur = $this->creerUtilisateurAvecBoutique();

        DB::table('sessions')->insert([
            'id' => 'session-de-test',
            'user_id' => $utilisateur->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('donnee-test'),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($superAdmin)->patch("/admin/utilisateurs/{$utilisateur->id}/basculer");

        $this->assertDatabaseMissing('sessions', ['user_id' => $utilisateur->id]);
    }

    // Filet EnsureAccountActive : un utilisateur désactivé qui tente malgré tout une
    // requête authentifiée (session déjà en cours dans un autre navigateur, par exemple)
    // est immédiatement déconnecté et redirigé vers /login.
    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $utilisateur = $this->creerUtilisateurAvecBoutique();
        $utilisateur->forceFill(['est_actif' => false])->save();

        $response = $this->actingAs($utilisateur)->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    // Blocage à la connexion elle-même (Fortify::authenticateUsing) : un compte désactivé
    // ne peut pas se connecter, même avec le bon mot de passe, et reçoit un message
    // explicite plutôt que l'erreur générique "identifiants invalides".
    public function test_deactivated_user_cannot_log_in_even_with_correct_password(): void
    {
        $utilisateur = $this->creerUtilisateurAvecBoutique();
        $utilisateur->forceFill(['password' => Hash::make('mot-de-passe-secret'), 'est_actif' => false])->save();

        $response = $this->post('/login', [
            'email' => $utilisateur->email,
            'password' => 'mot-de-passe-secret',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reactivated_user_can_log_in_again(): void
    {
        $utilisateur = $this->creerUtilisateurAvecBoutique();
        $utilisateur->forceFill(['password' => Hash::make('mot-de-passe-secret'), 'est_actif' => false])->save();

        $response = $this->post('/login', [
            'email' => $utilisateur->email,
            'password' => 'mot-de-passe-secret',
        ]);
        $this->assertGuest();

        $utilisateur->forceFill(['est_actif' => true])->save();

        $response = $this->post('/login', [
            'email' => $utilisateur->email,
            'password' => 'mot-de-passe-secret',
        ]);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($utilisateur->fresh());
    }

    // TEST 5 — suppression d'une boutique précise : cascade sur ses données, mais ne
    // touche jamais les autres boutiques du même utilisateur.
    public function test_super_admin_can_delete_a_specific_boutique_without_touching_others(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateur = $this->creerUtilisateurAvecBoutique('gratuit', ['nom' => 'Boutique A']);
        $boutiqueA = $utilisateur->currentBoutique;
        $boutiqueB = Boutique::create(['user_id' => $utilisateur->id, 'nom' => 'Boutique B', 'slug' => 'boutique-b-'.uniqid(), 'devise' => 'XAF']);
        Client::create(['boutique_id' => $boutiqueA->id, 'nom' => 'Client A', 'etiquette' => 'client']);

        $response = $this->actingAs($superAdmin)->delete("/admin/utilisateurs/{$utilisateur->id}/boutiques/{$boutiqueA->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('boutiques', ['id' => $boutiqueA->id]);
        $this->assertDatabaseMissing('clients', ['boutique_id' => $boutiqueA->id]);
        $this->assertDatabaseHas('boutiques', ['id' => $boutiqueB->id]);
        $this->assertDatabaseHas('users', ['id' => $utilisateur->id]);
    }

    // Un utilisateur ne peut pas se faire supprimer une boutique appartenant à quelqu'un d'autre.
    public function test_cannot_delete_a_boutique_that_does_not_belong_to_the_targeted_user(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateurA = $this->creerUtilisateurAvecBoutique();
        $utilisateurB = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($superAdmin)->delete("/admin/utilisateurs/{$utilisateurA->id}/boutiques/{$utilisateurB->current_boutique_id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('boutiques', ['id' => $utilisateurB->current_boutique_id]);
    }

    // TEST 6 — suppression complète du compte : cascade totale, audit conservé malgré la
    // disparition du compte visé (resource_id n'est pas une clé étrangère).
    public function test_super_admin_can_permanently_delete_a_user_account(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $utilisateur = $this->creerUtilisateurAvecBoutique();
        $boutiqueId = $utilisateur->current_boutique_id;
        Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client A', 'etiquette' => 'client']);

        $response = $this->actingAs($superAdmin)->delete("/admin/utilisateurs/{$utilisateur->id}");

        $response->assertRedirect(route('admin.utilisateurs.index'));
        $this->assertDatabaseMissing('users', ['id' => $utilisateur->id]);
        $this->assertDatabaseMissing('boutiques', ['id' => $boutiqueId]);
        $this->assertDatabaseMissing('clients', ['boutique_id' => $boutiqueId]);
        $this->assertDatabaseHas('admin_audits', [
            'admin_id' => $superAdmin->id,
            'action' => 'utilisateur_supprime',
            'resource_id' => $utilisateur->id,
        ]);
    }

    // TEST 7 — cette route ne doit jamais pouvoir cibler un administrateur ou le Super
    // Admin lui-même : garde structurelle (role === ROLE_USER uniquement).
    public function test_cannot_target_an_admin_or_super_admin_account_via_this_controller(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $autreSuperAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->get("/admin/utilisateurs/{$admin->id}")->assertStatus(404);
        $this->actingAs($superAdmin)->patch("/admin/utilisateurs/{$admin->id}/basculer")->assertStatus(404);
        $this->actingAs($superAdmin)->delete("/admin/utilisateurs/{$autreSuperAdmin->id}")->assertStatus(404);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('users', ['id' => $autreSuperAdmin->id]);
    }

    // TEST 8 — la permission utilisateurs.supprimer peut être déléguée à un admin
    // classique (cohérent avec le reste du système de permissions granulaires).
    public function test_delegated_admin_with_supprimer_permission_can_delete_a_boutique(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AdminPermission::create(['user_id' => $admin->id, 'permission' => 'utilisateurs.voir']);
        AdminPermission::create(['user_id' => $admin->id, 'permission' => 'utilisateurs.supprimer']);

        $utilisateur = $this->creerUtilisateurAvecBoutique();
        $boutiqueId = $utilisateur->current_boutique_id;

        $this->actingAs($admin)->delete("/admin/utilisateurs/{$utilisateur->id}/boutiques/{$boutiqueId}")->assertRedirect();

        $this->assertDatabaseMissing('boutiques', ['id' => $boutiqueId]);
    }
}
