<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class DepenseControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_create_list_and_delete_a_depense_via_the_api(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $creation = $this->actingAs($user, 'sanctum')->postJson('/api/depenses', [
            'categorie' => 'Loyer',
            'montant' => 50000,
            'date_depense' => now()->toDateString(),
        ]);
        $creation->assertCreated()->assertJsonPath('montant', '50000.00');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/depenses')
            ->assertOk()
            ->assertJsonCount(1, 'depenses.data');

        $depenseId = $creation->json('id');
        $this->actingAs($user, 'sanctum')->deleteJson("/api/depenses/{$depenseId}")->assertOk();
        $this->assertDatabaseMissing('depenses', ['id' => $depenseId]);
    }
}
