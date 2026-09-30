<?php

namespace Tests\Feature;

use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ProduitAjoutDepuisBoutiqueTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'produit',
            'nom' => 'Produit ajouté depuis la boutique',
            'prix_vente' => 5000,
            'unite' => 'pièce',
        ], $overrides);
    }

    public function test_owner_can_add_a_product_from_their_public_boutique_page_and_it_is_immediately_visible(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $response = $this->actingAs($user)->post(route('public.boutique.produits.store', $boutique->slug), $this->payload([
            'mini_characteristics' => 'RAM 8 Go • SSD 256 Go',
        ]));

        $response->assertRedirect(route('public.boutique', $boutique->slug));
        $this->assertDatabaseHas('produits', [
            'boutique_id' => $boutique->id,
            'nom' => 'Produit ajouté depuis la boutique',
            'mini_characteristics' => 'RAM 8 Go • SSD 256 Go',
            'actif' => 1,
            'marketplace_visible' => 1,
            'gere_stock' => 0,
        ]);

        // Le produit doit apparaître immédiatement sur la page publique de la boutique.
        $this->get(route('public.boutique', $boutique->slug))
            ->assertInertia(fn ($page) => $page->has('produits', 1));
    }

    public function test_visitor_cannot_see_the_add_button_prop_or_use_the_endpoint_for_someone_elses_boutique(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique();
        $visiteur = $this->creerUtilisateurAvecBoutique();
        $boutique = $proprietaire->currentBoutique;

        // La page publique n'indique jamais au visiteur qu'il est propriétaire.
        $this->get(route('public.boutique', $boutique->slug))
            ->assertInertia(fn ($page) => $page->where('estProprietaire', false));

        $this->actingAs($visiteur)
            ->get(route('public.boutique', $boutique->slug))
            ->assertInertia(fn ($page) => $page->where('estProprietaire', false));

        // Un utilisateur connecté mais non propriétaire ne peut pas créer de produit
        // dans la boutique d'un autre, même en appelant directement l'endpoint.
        $response = $this->actingAs($visiteur)->post(route('public.boutique.produits.store', $boutique->slug), $this->payload());

        $response->assertForbidden();
        $this->assertSame(0, Produit::withoutGlobalScopes()->where('boutique_id', $boutique->id)->count());
    }

    public function test_guest_cannot_use_the_endpoint(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique();
        $boutique = $proprietaire->currentBoutique;

        $response = $this->post(route('public.boutique.produits.store', $boutique->slug), $this->payload());

        $response->assertRedirect(route('login'));
    }

    public function test_product_lands_in_the_correct_boutique_even_if_the_owners_current_boutique_is_a_different_one(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutiqueA = $user->currentBoutique;

        // L'utilisateur possède une seconde boutique et l'a sélectionnée comme
        // "courante" -- ne doit jamais influencer dans quelle boutique le produit
        // atterrit lorsqu'il ajoute depuis la page publique de la boutique A.
        $boutiqueB = \App\Models\Boutique::create([
            'user_id' => $user->id,
            'nom' => 'Deuxième boutique',
            'slug' => 'boutique-b-'.uniqid(),
            'devise' => 'XAF',
            'taux_tva_defaut' => 19.25,
        ]);
        $user->switchBoutique($boutiqueB);
        $this->assertSame($boutiqueB->id, $user->fresh()->current_boutique_id);

        $this->actingAs($user)->post(route('public.boutique.produits.store', $boutiqueA->slug), $this->payload())
            ->assertRedirect(route('public.boutique', $boutiqueA->slug));

        $this->assertDatabaseHas('produits', [
            'boutique_id' => $boutiqueA->id,
            'nom' => 'Produit ajouté depuis la boutique',
        ]);
        $this->assertDatabaseMissing('produits', [
            'boutique_id' => $boutiqueB->id,
            'nom' => 'Produit ajouté depuis la boutique',
        ]);
    }

    public function test_creation_is_blocked_beyond_plan_limit(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', []);
        $user->planActif()->update(['limite_produits' => 1]);
        $boutique = $user->currentBoutique;

        Produit::create(['boutique_id' => $boutique->id, 'type' => 'produit', 'nom' => 'Existant', 'prix_vente' => 100, 'unite' => 'pièce']);

        $response = $this->actingAs($user)->post(route('public.boutique.produits.store', $boutique->slug), $this->payload(['nom' => 'En trop']));

        $response->assertSessionHas('flash_error');
        $this->assertDatabaseMissing('produits', ['nom' => 'En trop']);
    }

    public function test_the_normal_produits_creation_flow_still_defaults_to_invisible(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($user)->post(route('produits.store'), $this->payload(['nom' => 'Créé via Produits & Services', 'gere_stock' => false]))
            ->assertRedirect(route('produits.index'));

        $this->assertDatabaseHas('produits', [
            'nom' => 'Créé via Produits & Services',
            'marketplace_visible' => 0,
        ]);
    }
}
