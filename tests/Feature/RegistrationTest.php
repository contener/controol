<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_screen_cannot_be_rendered_if_support_is_disabled(): void
    {
        if (Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is enabled.');
        }

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_can_register(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration support is not enabled.');
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    /**
     * L'inscription vaut création de la boutique (2026-11-13) : le nouvel inscrit
     * arrive directement sur le tableau de bord de sa boutique, jamais sur l'écran
     * "Créez votre première boutique".
     */
    public function test_registration_creates_the_shop_with_the_given_name(): void
    {
        $this->post('/register', [
            'name' => 'Aïcha Ndongo',
            'nom_boutique' => 'Aïcha Mode',
            'email' => 'aicha@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $user = \App\Models\User::where('email', 'aicha@example.com')->firstOrFail();
        $boutique = $user->boutiques()->firstOrFail();

        $this->assertSame('Aïcha Mode', $boutique->nom);
        $this->assertSame('aicha-mode', $boutique->slug);
        $this->assertSame($boutique->id, $user->current_boutique_id);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('aucuneBoutique')->where('boutique.id', $boutique->id));
    }

    public function test_registration_without_shop_name_uses_a_default_name(): void
    {
        $this->post('/register', [
            'name' => 'Paul Biya',
            'email' => 'paul@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $user = \App\Models\User::where('email', 'paul@example.com')->firstOrFail();

        $this->assertSame(1, $user->boutiques()->count());
        $this->assertSame('Boutique de Paul Biya', $user->currentBoutique->nom);
    }
}
