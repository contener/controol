<?php

namespace Tests\Feature;

use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ProduitMiniCaracteristiquesTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_product_can_be_created_with_mini_characteristics(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($user)->post(route('produits.store'), [
            'type' => 'produit',
            'nom' => 'Ordinateur Lenovo ThinkPad',
            'prix_vente' => 250000,
            'unite' => 'pièce',
            'mini_characteristics' => 'Core i5 • RAM 8 Go • SSD 256 Go',
        ])->assertRedirect(route('produits.index'));

        $this->assertDatabaseHas('produits', [
            'nom' => 'Ordinateur Lenovo ThinkPad',
            'mini_characteristics' => 'Core i5 • RAM 8 Go • SSD 256 Go',
        ]);
    }

    public function test_product_can_be_created_without_mini_characteristics(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($user)->post(route('produits.store'), [
            'type' => 'produit',
            'nom' => 'Produit sans mini caractéristiques',
            'prix_vente' => 1000,
            'unite' => 'pièce',
        ])->assertRedirect(route('produits.index'));

        $this->assertDatabaseHas('produits', [
            'nom' => 'Produit sans mini caractéristiques',
            'mini_characteristics' => null,
        ]);
    }

    public function test_mini_characteristics_beyond_250_characters_is_rejected(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($user)->post(route('produits.store'), [
            'type' => 'produit',
            'nom' => 'Produit texte trop long',
            'prix_vente' => 1000,
            'unite' => 'pièce',
            'mini_characteristics' => str_repeat('a', 251),
        ]);

        $response->assertSessionHasErrors('mini_characteristics');
        $this->assertDatabaseMissing('produits', ['nom' => 'Produit texte trop long']);
    }

    public function test_mini_characteristics_can_be_updated_and_cleared(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $produit = Produit::create([
            'boutique_id' => $user->current_boutique_id,
            'type' => 'produit',
            'nom' => 'Produit existant',
            'prix_vente' => 5000,
            'unite' => 'pièce',
            'mini_characteristics' => 'Ancienne valeur',
        ]);

        $this->actingAs($user)->put(route('produits.update', $produit), [
            'type' => 'produit',
            'nom' => $produit->nom,
            'prix_vente' => $produit->prix_vente,
            'unite' => $produit->unite,
            'mini_characteristics' => '',
        ])->assertRedirect(route('produits.index'));

        $this->assertSame('', $produit->fresh()->mini_characteristics);
    }

    public function test_public_boutique_page_exposes_mini_characteristics(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', ['slug' => 'boutique-mini-carac']);

        Produit::create([
            'boutique_id' => $user->current_boutique_id,
            'type' => 'produit',
            'nom' => 'Produit avec mini caractéristiques',
            'prix_vente' => 1000,
            'unite' => 'pièce',
            'actif' => true,
            'mini_characteristics' => 'Écran 6,5 pouces • 128 Go • Double SIM',
        ]);

        $response = $this->get('/boutique/boutique-mini-carac');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Boutique')
            ->where('produits.0.mini_characteristics', 'Écran 6,5 pouces • 128 Go • Double SIM'));
    }

    public function test_public_boutique_page_handles_products_without_mini_characteristics(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', ['slug' => 'boutique-sans-mini-carac']);

        Produit::create([
            'boutique_id' => $user->current_boutique_id,
            'type' => 'produit',
            'nom' => 'Produit classique',
            'prix_vente' => 1000,
            'unite' => 'pièce',
            'actif' => true,
        ]);

        $response = $this->get('/boutique/boutique-sans-mini-carac');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Boutique')
            ->where('produits.0.mini_characteristics', null));
    }
}
