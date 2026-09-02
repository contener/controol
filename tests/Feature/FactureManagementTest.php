<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class FactureManagementTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_facture_creation_computes_totals_and_generates_sequential_numbers(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro', ['taux_tva_defaut' => 20]);
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
            'quantite_stock' => 50,
        ]);

        $this->actingAs($user);

        $payload = [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                [
                    'produit_id' => $produit->id,
                    'designation' => $produit->nom,
                    'quantite' => 3,
                    'prix_unitaire' => 100,
                    'tva_taux' => 20,
                    'remise_ligne' => 0,
                ],
            ],
        ];

        $response = $this->post('/factures', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('factures', [
            'boutique_id' => $boutiqueId,
            'sous_total' => 300,
            'total_tva' => 60,
            'total_ttc' => 360,
        ]);

        $this->assertEquals(47, $produit->fresh()->quantite_stock);
        $this->assertDatabaseHas('mouvements_stock', [
            'produit_id' => $produit->id,
            'type' => 'sortie',
            'quantite' => 3,
        ]);

        // Deuxième facture : le numéro doit s'incrémenter.
        $this->post('/factures', $payload);

        $numeros = \App\Models\Facture::withoutGlobalScopes()->pluck('numero')->sort()->values();
        $annee = now()->year;
        $this->assertEquals("FAC-{$annee}-0001", $numeros[0]);
        $this->assertEquals("FAC-{$annee}-0002", $numeros[1]);
    }

    public function test_cancelling_facture_restores_stock(): void
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
            'tva_taux' => 0,
            'gere_stock' => true,
            'quantite_stock' => 10,
        ]);

        $this->actingAs($user);

        $this->post('/factures', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                [
                    'produit_id' => $produit->id,
                    'designation' => $produit->nom,
                    'quantite' => 4,
                    'prix_unitaire' => 100,
                    'tva_taux' => 0,
                    'remise_ligne' => 0,
                ],
            ],
        ]);

        $this->assertEquals(6, $produit->fresh()->quantite_stock);

        $facture = \App\Models\Facture::first();
        $this->patch("/factures/{$facture->id}/statut", ['statut' => 'annulee']);

        $this->assertEquals(10, $produit->fresh()->quantite_stock);
    }

    public function test_user_cannot_create_facture_referencing_client_from_another_boutique(): void
    {
        $userA = $this->creerUtilisateurAvecBoutique();
        $userB = $this->creerUtilisateurAvecBoutique();

        $clientB = Client::create(['boutique_id' => $userB->current_boutique_id, 'nom' => 'Client B', 'etiquette' => 'client']);

        $this->actingAs($userA);

        $response = $this->post('/factures', [
            'client_id' => $clientB->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['designation' => 'Ligne libre', 'quantite' => 1, 'prix_unitaire' => 10, 'tva_taux' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_facture_creation_is_blocked_beyond_plan_limit(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $user->planActif()->update(['limite_factures' => 0]);
        $boutiqueId = $user->current_boutique_id;

        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client A', 'etiquette' => 'client']);

        $this->actingAs($user);

        $response = $this->post('/factures', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['designation' => 'Ligne libre', 'quantite' => 1, 'prix_unitaire' => 10, 'tva_taux' => 0],
            ],
        ]);

        $response->assertSessionHas('flash_error');
        $this->assertSame(0, \App\Models\Facture::count());
    }
}
