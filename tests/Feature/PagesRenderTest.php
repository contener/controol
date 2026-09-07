<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Depense;
use App\Models\Produit;
use App\Services\FactureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_all_main_management_pages_render_successfully(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutiqueId = $user->current_boutique_id;

        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client A', 'etiquette' => 'client']);
        $produit = Produit::create([
            'boutique_id' => $boutiqueId,
            'type' => 'produit',
            'nom' => 'Widget',
            'prix_vente' => 100,
            'unite' => 'pièce',
            'tva_taux' => 20,
            'gere_stock' => true,
            'quantite_stock' => 10,
            'seuil_alerte' => 2,
        ]);

        $depense = Depense::create([
            'boutique_id' => $boutiqueId,
            'categorie' => 'Loyer',
            'montant' => 5000,
            'date_depense' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $facture = app(FactureService::class)->creer([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                [
                    'produit_id' => $produit->id,
                    'designation' => $produit->nom,
                    'quantite' => 1,
                    'prix_unitaire' => 100,
                    'tva_taux' => 20,
                    'remise_ligne' => 0,
                ],
            ],
        ], $user, $boutiqueId);

        $this->actingAs($user);

        foreach ([
            '/dashboard',
            '/compte/dashboard',
            '/boutiques',
            '/boutiques/create',
            "/boutiques/{$boutiqueId}/edit",
            '/clients',
            '/clients/create',
            "/clients/{$client->id}/edit",
            '/produits',
            '/produits/create',
            "/produits/{$produit->id}/edit",
            '/stock',
            "/stock/{$produit->id}/mouvements",
            '/factures',
            '/factures/create',
            '/factures/create?modele=2',
            '/factures/modeles',
            "/factures/{$facture->id}",
            "/factures/{$facture->id}/edit",
            '/depenses',
            '/depenses/create',
            "/depenses/{$depense->id}/edit",
            '/messages',
            '/mes-conversations',
            '/abonnement',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get("/factures/{$facture->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // Chacun des 10 modèles doit avoir une vue PDF fonctionnelle (aucune vue manquante).
        foreach (range(1, 10) as $modeleId) {
            $facture->update(['modele_id' => $modeleId]);
            $this->get("/factures/{$facture->id}/pdf")
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_public_boutique_storefront_is_accessible_without_authentication(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', ['slug' => 'ma-super-boutique', 'statut' => 'active']);

        Produit::create([
            'boutique_id' => $user->current_boutique_id,
            'type' => 'produit',
            'nom' => 'Widget public',
            'prix_vente' => 1000,
            'unite' => 'pièce',
            'actif' => true,
        ]);

        $this->get('/boutique/ma-super-boutique')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/Boutique'));
    }
}
