<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authentification par jeton (voir AuthController) -- premier point de connexion
 * entre l'application mobile Flutter (flutter_app/) et ce backend.
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_a_usable_token(): void
    {
        // two_factor_confirmed_at à null : la factory l'active par défaut (voir
        // UserFactory), ce qui bloquerait sinon la connexion via le contrôle 2FA.
        $user = User::factory()->create(['password' => bcrypt('mot-de-passe-solide'), 'two_factor_confirmed_at' => null]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-solide',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
        $token = $response->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('mot-de-passe-solide')]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_with_unknown_email_is_rejected(): void
    {
        $this->postJson('/api/login', [
            'email' => 'personne@example.com',
            'password' => 'peu-importe',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_disabled_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('mot-de-passe-solide'),
            'est_actif' => false,
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-solide',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /**
     * Pas de flux de confirmation 2FA côté mobile pour l'instant (voir AuthController)
     * -- bloqué explicitement plutôt que silencieusement contourné.
     */
    public function test_account_with_confirmed_two_factor_is_blocked_with_a_clear_message(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('mot-de-passe-solide'),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-solide',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('double authentification', $response->json('errors.email.0'));
    }

    public function test_register_creates_an_account_and_returns_a_usable_token(): void
    {
        // NouvelUtilisateurService ne démarre un essai/plan que si un Plan existe en
        // base -- un test RefreshDatabase n'en a aucun par défaut (voir EssaiBasiqueTest).
        Plan::create(['code' => 'gratuit', 'nom' => 'Gratuit', 'prix' => 0, 'devise' => 'XAF']);

        $response = $this->postJson('/api/register', [
            'name' => 'Nouvel Utilisateur',
            'email' => 'nouveau@example.com',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email']]);
        $this->assertDatabaseHas('users', ['email' => 'nouveau@example.com']);

        // Un plan (essai ou gratuit) a bien été démarré -- voir NouvelUtilisateurService,
        // même effet de bord que l'inscription web.
        $user = User::where('email', 'nouveau@example.com')->first();
        $this->assertNotNull($user->abonnements()->first());

        $token = $response->json('token');
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    /** Même règle que le web : l'inscription mobile crée aussi la boutique. */
    public function test_register_via_api_creates_the_shop(): void
    {
        Plan::create(['code' => 'gratuit', 'nom' => 'Gratuit', 'prix' => 0, 'devise' => 'XAF']);

        $token = $this->postJson('/api/register', [
            'name' => 'Nouvel Utilisateur',
            'nom_boutique' => 'Ma Boutique Mobile',
            'email' => 'mobile@example.com',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertCreated()->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('boutique.nom', 'Ma Boutique Mobile');
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Nouvel Utilisateur',
            'email' => 'nouveau@example.com',
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'autre-chose',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nouveau@example.com']);
    }

    public function test_register_rejects_an_already_used_email(): void
    {
        $existant = User::factory()->create();

        $this->postJson('/api/register', [
            'name' => 'Quelqu\'un',
            'email' => $existant->email,
            'password' => 'mot-de-passe-solide',
            'password_confirmation' => 'mot-de-passe-solide',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('mot-de-passe-solide'), 'two_factor_confirmed_at' => null]);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-solide',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        // Le jeton est bien supprimé de la base -- on vérifie directement la ligne
        // plutôt que de rejouer une requête authentifiée avec le même jeton : le
        // guard Sanctum met en cache l'utilisateur résolu sur l'instance de
        // l'application, qui persiste entre deux appels HTTP simulés dans un même
        // test (contrairement à une vraie requête, qui démarre une application
        // fraîche), ce qui ferait passer à tort un jeton révoqué pour valide ici.
        [$id] = explode('|', $token, 2);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }
}
