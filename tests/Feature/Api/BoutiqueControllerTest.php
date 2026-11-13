<?php

namespace Tests\Feature\Api;

use App\Models\Boutique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class BoutiqueControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_lists_owned_boutiques_with_current_id(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/boutiques')
            ->assertOk()
            ->assertJsonPath('boutique_courante_id', $user->current_boutique_id)
            ->assertJsonPath('boutiques.0.est_proprietaire', true);
    }

    public function test_can_switch_to_another_owned_boutique(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $autreBoutique = Boutique::create([
            'user_id' => $user->id, 'nom' => 'Deuxième boutique',
            'slug' => 'deuxieme-'.uniqid(), 'devise' => 'XAF', 'taux_tva_defaut' => 19.25,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/boutiques/{$autreBoutique->id}/switch")
            ->assertOk();

        $this->assertSame($autreBoutique->id, $user->fresh()->current_boutique_id);
    }

    public function test_cannot_switch_to_a_boutique_belonging_to_someone_else(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $autre = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/boutiques/{$autre->current_boutique_id}/switch")
            ->assertStatus(403);
    }
}
