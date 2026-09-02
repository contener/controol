<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_user_can_update_language_and_theme(): void
    {
        $user = User::factory()->create(['locale' => 'fr', 'theme' => 'light']);
        $this->actingAs($user);

        $response = $this->patch('/preferences', [
            'locale' => 'en',
            'theme' => 'dark',
        ]);

        $response->assertRedirect();
        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('dark', $user->fresh()->theme);
    }

    public function test_preferences_require_valid_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->patch('/preferences', [
            'locale' => 'de',
            'theme' => 'neon',
        ]);

        $response->assertSessionHasErrors(['locale', 'theme']);
    }

    public function test_locale_preference_changes_backend_validation_language(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $user->update(['locale' => 'en']);
        $this->actingAs($user);

        // 'nom' is required on client creation: an English-locale user should see
        // the English validation message, proving SetLocale is applied server-side.
        $response = $this->post('/clients', ['etiquette' => 'prospect']);

        $response->assertSessionHasErrors('nom');
        $messages = session('errors')->getBag('default')->get('nom');
        $this->assertStringContainsString('required', $messages[0]);
        $this->assertStringNotContainsString('obligatoire', $messages[0]);
    }
}
