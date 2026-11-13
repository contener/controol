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
            'boutique' => ['nom' => 'Test Boutique'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }


    /**
     * L'inscription est la création de la première boutique (2026-11-13) : tous les
     * champs du formulaire en étapes (y compris logo et bannière) sont enregistrés,
     * la boutique devient la boutique courante, et le nouvel inscrit arrive
     * directement sur son tableau de bord -- jamais sur "Créez votre première boutique".
     */
    public function test_registration_creates_the_first_shop_with_all_its_details(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $this->post('/register', [
            'name' => 'Aïcha Ndongo',
            'email' => 'aicha@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'boutique' => [
                'nom' => 'Aïcha Mode',
                'categorie' => 'Vêtements',
                'description' => 'Pagnes et robes sur mesure.',
                'ville' => 'Douala',
                'pays' => 'Cameroun',
                'adresse' => 'Akwa',
                'telephone' => '+237600000001',
                'whatsapp' => '+237600000002',
                'email' => 'contact@aicha-mode.cm',
                'devise' => 'XAF',
                'taux_tva_defaut' => '19.25',
                'nui' => 'M012312345678A',
                'note_pied_facture' => 'Ni repris ni échangé.',
                'facebook_url' => 'https://facebook.com/aichamode',
                'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 200),
                'banniere' => \Illuminate\Http\UploadedFile::fake()->image('banniere.jpg', 1200, 400),
            ],
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = \App\Models\User::where('email', 'aicha@example.com')->firstOrFail();
        $boutique = $user->boutiques()->firstOrFail();

        $this->assertSame(1, $user->boutiques()->count());
        $this->assertSame($boutique->id, $user->current_boutique_id);
        $this->assertSame('Aïcha Mode', $boutique->nom);
        $this->assertSame('aicha-mode', $boutique->slug);
        $this->assertSame('Douala', $boutique->ville);
        $this->assertSame('+237600000002', $boutique->whatsapp);
        $this->assertSame('contact@aicha-mode.cm', $boutique->email);
        $this->assertSame('M012312345678A', $boutique->nui);
        $this->assertNotNull($boutique->logo_path);
        $this->assertNotNull($boutique->banniere_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists([$boutique->logo_path, $boutique->banniere_path]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('aucuneBoutique')->where('boutique.id', $boutique->id));
    }

    public function test_shop_name_is_required_to_register(): void
    {
        $this->post('/register', [
            'name' => 'Paul',
            'email' => 'paul@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'boutique' => ['nom' => '', 'facebook_url' => 'pas-une-url'],
        ])->assertSessionHasErrors(['boutique.nom', 'boutique.facebook_url']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'paul@example.com']);
    }

    /** Les boutiques suivantes se créent toujours depuis "Mes boutiques". */
    public function test_user_can_still_create_additional_shops_after_registering(): void
    {
        \App\Models\Plan::create(['code' => 'pro', 'nom' => 'Pro', 'prix' => 0, 'devise' => 'XAF', 'limite_boutiques' => null]);

        $this->post('/register', [
            'name' => 'Multi',
            'email' => 'multi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
            'boutique' => ['nom' => 'Première'],
        ]);

        $user = \App\Models\User::where('email', 'multi@example.com')->firstOrFail();
        $user->abonnements()->create(['plan_id' => \App\Models\Plan::where('code', 'pro')->value('id'), 'statut' => 'actif', 'date_debut' => now()->addSecond()]);

        $this->post(route('boutiques.store'), ['nom' => 'Deuxième'])->assertRedirect(route('boutiques.index'));

        $this->assertSame(['Deuxième', 'Première'], $user->boutiques()->orderBy('nom')->pluck('nom')->all());
        $this->assertSame('premiere', $user->fresh()->currentBoutique->slug);
    }
}
