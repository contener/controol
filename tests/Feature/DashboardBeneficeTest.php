<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Depense;
use App\Models\Produit;
use App\Services\BoutiqueStatsService;
use App\Services\FactureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Le tableau de bord affichait déjà chiffre d'affaires / dépenses / "résultat" (CA −
 * dépenses de fonctionnement), mais jamais le bénéfice réel basé sur le coût d'achat
 * des produits vendus -- voir BoutiqueStatsService::benefice().
 */
class DashboardBeneficeTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function creerFacture($user, int $boutiqueId, array $lignes, array $overrides = []): void
    {
        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client Test', 'etiquette' => 'client']);

        app(FactureService::class)->creer(array_merge([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'statut' => 'payee',
            'remise' => 0,
            'lignes' => $lignes,
        ], $overrides), $user, $boutiqueId);
    }

    public function test_benefice_is_computed_from_purchase_and_sale_price(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        $produit = Produit::create([
            'boutique_id' => $boutiqueId, 'type' => 'produit', 'nom' => 'Widget',
            'prix_achat' => 600, 'prix_vente' => 1000, 'unite' => 'pièce',
        ]);

        $this->creerFacture($user, $boutiqueId, [[
            'produit_id' => $produit->id,
            'designation' => 'Widget',
            'quantite' => 3,
            'prix_unitaire' => 1000,
            'tva_taux' => 0,
            'remise_ligne' => 0,
        ]]);

        $stats = app(BoutiqueStatsService::class)->pourBoutique($user->currentBoutique);

        // (1000 − 600) × 3 = 1200, sur le CA du mois comme sur la période (mois par défaut).
        $this->assertSame(1200.0, $stats['finances']['benefice_mois']);
        $this->assertSame(1200.0, $stats['periode']['benefice']);
        $this->assertSame(3000.0, $stats['periode']['ca']);
        $this->assertSame(40.0, $stats['periode']['marge_pourcentage']);
    }

    public function test_free_text_lines_without_a_product_count_fully_as_profit(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;

        $this->creerFacture($user, $boutiqueId, [[
            'produit_id' => null,
            'designation' => 'Prestation sur mesure',
            'quantite' => 1,
            'prix_unitaire' => 500,
            'tva_taux' => 0,
            'remise_ligne' => 0,
        ]]);

        $stats = app(BoutiqueStatsService::class)->pourBoutique($user->currentBoutique);

        $this->assertSame(500.0, $stats['finances']['benefice_mois']);
    }

    public function test_draft_and_cancelled_invoices_are_excluded_from_benefice(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        $produit = Produit::create([
            'boutique_id' => $boutiqueId, 'type' => 'produit', 'nom' => 'Widget',
            'prix_achat' => 600, 'prix_vente' => 1000, 'unite' => 'pièce',
        ]);

        $this->creerFacture($user, $boutiqueId, [[
            'produit_id' => $produit->id,
            'designation' => 'Widget',
            'quantite' => 1,
            'prix_unitaire' => 1000,
            'tva_taux' => 0,
            'remise_ligne' => 0,
        ]], ['statut' => 'brouillon']);

        $stats = app(BoutiqueStatsService::class)->pourBoutique($user->currentBoutique);

        $this->assertSame(0.0, $stats['finances']['benefice_mois']);
    }

    public function test_custom_period_filters_revenue_expenses_and_benefice(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        $produit = Produit::create([
            'boutique_id' => $boutiqueId, 'type' => 'produit', 'nom' => 'Widget',
            'prix_achat' => 600, 'prix_vente' => 1000, 'unite' => 'pièce',
        ]);

        // Dans la période ciblée.
        $this->creerFacture($user, $boutiqueId, [[
            'produit_id' => $produit->id, 'designation' => 'Widget', 'quantite' => 1,
            'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0,
        ]], ['date_emission' => '2026-01-15']);

        // Hors période ciblée -- ne doit jamais être compté.
        $this->creerFacture($user, $boutiqueId, [[
            'produit_id' => $produit->id, 'designation' => 'Widget', 'quantite' => 10,
            'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0,
        ]], ['date_emission' => '2026-03-01']);

        Depense::create([
            'boutique_id' => $boutiqueId, 'categorie' => 'Loyer', 'montant' => 200,
            'date_depense' => '2026-01-20', 'created_by' => $user->id,
        ]);

        $stats = app(BoutiqueStatsService::class)->pourBoutique($user->currentBoutique, '2026-01-01', '2026-01-31');

        $this->assertSame(1000.0, $stats['periode']['ca']);
        $this->assertSame(200.0, $stats['periode']['depenses']);
        $this->assertSame(400.0, $stats['periode']['benefice']);
        $this->assertSame(800.0, $stats['periode']['resultat']);
        $this->assertSame(1, $stats['periode']['nombre_factures']);
        $this->assertSame(1000.0, $stats['periode']['panier_moyen']);
    }

    public function test_dashboard_page_accepts_period_query_params(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $this->actingAs($user);

        $this->get('/dashboard?debut=2026-01-01&fin=2026-01-31')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.periode.debut', '2026-01-01')
                ->where('stats.periode.fin', '2026-01-31'));
    }
}
