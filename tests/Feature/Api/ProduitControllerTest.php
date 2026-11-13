<?php

namespace Tests\Feature\Api;

use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ProduitControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_create_a_stock_managed_product_via_the_api(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/produits', [
                'type' => 'produit',
                'nom' => 'Widget',
                'prix_achat' => 600,
                'prix_vente' => 1000,
                'unite' => 'pièce',
                'gere_stock' => true,
                'quantite_stock' => 10,
            ])
            ->assertCreated()
            ->assertJsonPath('gere_stock', true)
            ->assertJsonPath('quantite_stock', 10);
    }

    public function test_a_service_never_tracks_stock_even_if_requested(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/produits', [
                'type' => 'service',
                'nom' => 'Consultation',
                'prix_vente' => 5000,
                'unite' => 'heure',
            ]);

        $response->assertCreated()->assertJsonPath('gere_stock', false);
    }

    public function test_list_and_delete_a_product_via_the_api(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $produit = Produit::create([
            'boutique_id' => $user->current_boutique_id, 'type' => 'produit',
            'nom' => 'Widget', 'prix_vente' => 100, 'unite' => 'pièce',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/produits')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/produits/{$produit->id}")->assertOk();
        $this->assertDatabaseMissing('produits', ['id' => $produit->id]);
    }
}
