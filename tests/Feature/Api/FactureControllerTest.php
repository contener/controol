<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * L'API réutilise FactureService pour toute la logique métier -- ces tests
 * vérifient surtout le branchement (auth, validation, réponses JSON), pas les
 * règles déjà couvertes par les tests web existants (voir tests/Feature/FactureTypeTest.php).
 */
class FactureControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function creerClientEtProduit(int $boutiqueId): array
    {
        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client A', 'etiquette' => 'client']);
        $produit = Produit::create([
            'boutique_id' => $boutiqueId, 'type' => 'produit', 'nom' => 'Widget',
            'prix_vente' => 100, 'unite' => 'pièce', 'tva_taux' => 20,
            'gere_stock' => true, 'quantite_stock' => 50,
        ]);

        return [$client, $produit];
    }

    public function test_create_a_facture_via_the_api_consumes_stock(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        [$client, $produit] = $this->creerClientEtProduit($user->current_boutique_id);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/factures', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [[
                'produit_id' => $produit->id, 'designation' => 'Widget',
                'quantite' => 3, 'prix_unitaire' => 100, 'tva_taux' => 20, 'remise_ligne' => 0,
            ]],
        ]);

        $response->assertCreated()->assertJsonPath('type', 'facture');
        $this->assertSame(47, $produit->fresh()->quantite_stock);
    }

    public function test_recu_requires_montant_avance_via_the_api(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        [$client] = $this->creerClientEtProduit($user->current_boutique_id);

        $this->actingAs($user, 'sanctum')->postJson('/api/factures', [
            'client_id' => $client->id,
            'type' => 'recu',
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [[
                'designation' => 'Prestation', 'quantite' => 1, 'prix_unitaire' => 300, 'tva_taux' => 0, 'remise_ligne' => 0,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('montant_avance');
    }

    public function test_show_exposes_the_same_apercu_totals_as_the_web(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        [$client] = $this->creerClientEtProduit($user->current_boutique_id);

        $this->actingAs($user, 'sanctum')->postJson('/api/factures', [
            'client_id' => $client->id,
            'type' => 'recu',
            'montant_avance' => 100,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [[
                'designation' => 'Prestation', 'quantite' => 1, 'prix_unitaire' => 300, 'tva_taux' => 0, 'remise_ligne' => 0,
            ]],
        ]);
        $facture = Facture::latest('id')->first();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/factures/{$facture->id}")
            ->assertOk()
            // Entiers, pas floats : json_encode() de PHP écrit un float sans partie
            // décimale comme un entier JSON (100, pas 100.0), donc json_decode() de
            // la réponse le relit comme un int -- la valeur est correcte, seul le
            // type PHP d'origine (float, voir FactureApercuBuilder) ne survit pas
            // l'aller-retour JSON.
            ->assertJsonPath('apercu.totaux.montant_avance', 100)
            ->assertJsonPath('apercu.totaux.reste_a_payer', 200);
    }

    public function test_user_cannot_view_a_facture_from_another_boutique(): void
    {
        $proprietaireA = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaireB = $this->creerUtilisateurAvecBoutique('pro');
        [$clientB] = $this->creerClientEtProduit($proprietaireB->current_boutique_id);

        $this->actingAs($proprietaireB, 'sanctum')->postJson('/api/factures', [
            'client_id' => $clientB->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [['designation' => 'X', 'quantite' => 1, 'prix_unitaire' => 10, 'tva_taux' => 0, 'remise_ligne' => 0]],
        ]);
        $facture = Facture::latest('id')->first();

        // 404, pas 403 : même scope global BelongsToBoutique que ClientControllerTest.
        $this->actingAs($proprietaireA, 'sanctum')
            ->getJson("/api/factures/{$facture->id}")
            ->assertStatus(404);
    }
}
