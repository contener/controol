<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_me_cookie_duration_is_thirty_days(): void
    {
        // getRememberDuration() est protected sur SessionGuard : lecture par reflection.
        $reflection = new \ReflectionProperty(Auth::guard('web'), 'rememberDuration');
        $reflection->setAccessible(true);

        $this->assertSame(60 * 24 * 30, $reflection->getValue(Auth::guard('web')));
    }

    public function test_security_headers_are_present_on_every_response(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_admin_without_confirmed_two_factor_is_redirected_to_profile(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'two_factor_confirmed_at' => null]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertRedirect(route('profile.show'));
        $response->assertSessionHas('flash_error');
    }

    public function test_super_admin_without_confirmed_two_factor_is_redirected_to_profile(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'two_factor_confirmed_at' => null]);

        $this->actingAs($superAdmin)->get('/admin')->assertRedirect(route('profile.show'));
    }

    public function test_admin_with_confirmed_two_factor_can_access_admin_space(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'two_factor_confirmed_at' => now()]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_regular_user_is_never_prompted_for_two_factor_on_normal_pages(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => null]);

        // Compte, pas boutique : accessible sans boutique courante, contrairement à
        // /dashboard -- confirme juste que la 2FA n'est exigée que sous /admin.
        $this->actingAs($user)->get('/compte/dashboard')->assertOk();
    }
}
