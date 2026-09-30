<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name = 'Jean Dupont', bool $emailVerified = true): SocialiteUser
    {
        $googleUser = new SocialiteUser();
        $googleUser->id = $id;
        $googleUser->name = $name;
        $googleUser->email = $email;
        $googleUser->user = ['email_verified' => $emailVerified];

        return $googleUser;
    }

    private function mockSocialiteReturning(SocialiteUser $googleUser): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_returns_a_redirect_response(): void
    {
        $this->get(route('auth.google.redirect'))->assertStatus(302);
    }

    public function test_new_user_is_created_and_logged_in_on_first_google_login(): void
    {
        Plan::firstOrCreate(['code' => 'gratuit'], ['nom' => 'Gratuit', 'prix' => 0]);
        $this->mockSocialiteReturning($this->fakeGoogleUser('g-123', 'nouveau@example.com'));

        $response = $this->get(route('auth.google.callback'));

        $user = User::where('email', 'nouveau@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertSame('Jean Dupont', $user->name);
        $this->assertTrue($user->est_actif);
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));

        // Les mêmes effets de bord qu'une inscription classique (essai/plan) doivent
        // s'être déclenchés, via NouvelUtilisateurService.
        $this->assertTrue(Abonnement::where('user_id', $user->id)->exists());
    }

    public function test_existing_email_password_account_is_linked_not_duplicated(): void
    {
        $existant = User::factory()->create(['email' => 'deja-inscrit@example.com', 'google_id' => null]);
        $this->mockSocialiteReturning($this->fakeGoogleUser('g-456', 'deja-inscrit@example.com'));

        $this->get(route('auth.google.callback'));

        $this->assertSame(1, User::where('email', 'deja-inscrit@example.com')->count());
        $this->assertSame('g-456', $existant->fresh()->google_id);
        $this->assertAuthenticatedAs($existant->fresh());
    }

    public function test_returning_google_user_is_found_by_google_id(): void
    {
        $existant = User::factory()->create(['email' => 'ancien-email-different@example.com', 'google_id' => 'g-789']);
        // Google renvoie parfois une adresse différente de celle stockée (changement
        // d'email côté Google) -- google_id doit rester la clé de correspondance
        // prioritaire, jamais l'email dans ce cas.
        $this->mockSocialiteReturning($this->fakeGoogleUser('g-789', 'nouvel-email@example.com'));

        $this->get(route('auth.google.callback'));

        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($existant->fresh());
    }

    public function test_disabled_account_cannot_log_in_via_google(): void
    {
        $desactive = User::factory()->create(['email' => 'desactive@example.com', 'est_actif' => false]);
        $this->mockSocialiteReturning($this->fakeGoogleUser('g-999', 'desactive@example.com'));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('flash_error');
        $this->assertGuest();
    }

    public function test_user_with_confirmed_two_factor_is_sent_to_the_challenge_not_logged_in_directly(): void
    {
        $protege = User::factory()->create([
            'email' => 'protege@example.com',
            'two_factor_secret' => 'un-secret-quelconque',
            'two_factor_confirmed_at' => now(),
        ]);
        $this->mockSocialiteReturning($this->fakeGoogleUser('g-2fa', 'protege@example.com'));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->assertSame($protege->id, Session::get('login.id'));
    }

    public function test_pending_referral_code_in_session_is_applied_to_a_new_google_signup(): void
    {
        Plan::firstOrCreate(['code' => 'gratuit'], ['nom' => 'Gratuit', 'prix' => 0]);
        $parrain = User::factory()->create(['code_parrainage' => 'ABCD1234']);
        Session::put('parrainage_code', 'ABCD1234');

        $this->mockSocialiteReturning($this->fakeGoogleUser('g-ref', 'filleul-google@example.com'));
        $this->get(route('auth.google.callback'));

        $filleul = User::where('email', 'filleul-google@example.com')->first();
        $this->assertSame($parrain->id, $filleul->parrain_id);
    }

    public function test_google_failure_redirects_to_login_with_flash_error(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Two\GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andThrow(new \Exception('invalid_state'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('flash_error');
        $this->assertGuest();
    }
}
