<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbonnementTest extends TestCase
{
    use RefreshDatabase;

    // Un plan payant avec lien_paiement : la demande crée un abonnement/paiement
    // "en_attente" (jamais actif) puis redirige en dur vers la page de paiement externe
    // via Inertia::location — nécessaire pour un domaine externe (MoneyFusion), un simple
    // router.post ne peut pas suivre une redirection cross-origin.
    public function test_choosing_a_paid_plan_with_payment_link_redirects_externally_without_activating(): void
    {
        $plan = Plan::create([
            'code' => 'basique', 'nom' => 'Basique', 'prix' => 5000, 'devise' => 'XAF',
            'lien_paiement' => 'https://my.moneyfusion.net/6a97318b21e2a15849259093',
        ]);
        $user = User::factory()->create();

        // Simule une visite Inertia réelle (router.post côté frontend envoie cet en-tête) :
        // Inertia::location() répond alors par un 409 + X-Inertia-Location que le client
        // JS transforme en redirection dure (window.location), seule façon de sortir vers
        // un domaine externe sans être bloqué par le CORS d'une requête XHR classique.
        $response = $this->actingAs($user)->withHeaders(['X-Inertia' => 'true'])
            ->post(route('abonnement.changer', $plan));

        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location', 'https://my.moneyfusion.net/6a97318b21e2a15849259093');

        $this->assertNull($user->fresh()->planActif(), "L'abonnement ne doit jamais être actif avant vérification du paiement.");
        $this->assertDatabaseHas('abonnements', ['user_id' => $user->id, 'plan_id' => $plan->id, 'statut' => 'en_attente']);
        $this->assertDatabaseHas('paiements', ['user_id' => $user->id, 'statut' => 'en_attente']);
    }

    // Un plan payant sans lien_paiement configuré : comportement de repli inchangé
    // (message flash), aucune erreur — permet d'ajouter un futur plan payant avant
    // même d'avoir sa page de paiement prête.
    public function test_choosing_a_paid_plan_without_payment_link_falls_back_to_flash_message(): void
    {
        $plan = Plan::create(['code' => 'pro', 'nom' => 'Pro', 'prix' => 15000, 'devise' => 'XAF']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('abonnement.changer', $plan));

        $response->assertRedirect();
        $response->assertSessionHas('flash_success');
        $this->assertDatabaseHas('paiements', ['user_id' => $user->id, 'statut' => 'en_attente']);
    }

    public function test_choosing_the_free_plan_activates_immediately_without_payment(): void
    {
        $plan = Plan::create(['code' => 'gratuit', 'nom' => 'Gratuit', 'prix' => 0, 'devise' => 'XAF']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('abonnement.changer', $plan))->assertRedirect();

        $this->assertSame('gratuit', $user->fresh()->planActif()->code);
        $this->assertDatabaseMissing('paiements', ['user_id' => $user->id]);
    }
}
