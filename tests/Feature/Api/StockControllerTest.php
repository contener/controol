<?php

namespace Tests\Feature\Api;

use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_lists_stock_managed_products_and_records_a_movement(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $produit = Produit::create([
            'boutique_id' => $user->current_boutique_id, 'type' => 'produit', 'nom' => 'Widget',
            'prix_vente' => 100, 'unite' => 'pièce', 'gere_stock' => true, 'quantite_stock' => 10,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/stock')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->postJson('/api/stock', [
            'produit_id' => $produit->id, 'type' => 'entree', 'quantite' => 5, 'motif' => 'Réapprovisionnement',
        ])->assertOk();

        $this->assertSame(15, $produit->fresh()->quantite_stock);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/stock/{$produit->id}/mouvements")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
