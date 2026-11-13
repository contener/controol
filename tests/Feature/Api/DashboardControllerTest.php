<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_returns_stats_for_the_current_boutique(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure(['boutique', 'stats' => ['finances' => ['ca_mois', 'benefice_mois'], 'periode']]);
    }

    /**
     * Même permission que le web (Boutique::autoriseDashboard()) -- un collaborateur
     * sans la permission "Dashboard" reçoit un 403 explicite côté API (pas d'état
     * "accès refusé doux" ici, ce raffinement étant spécifique à la page web qui est
     * aussi la page d'accueil post-connexion).
     */
    public function test_collaborator_without_dashboard_permission_is_rejected(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = \App\Models\User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $this->actingAs($collaborateur->fresh(), 'sanctum')
            ->getJson('/api/dashboard')
            ->assertStatus(403);
    }
}
