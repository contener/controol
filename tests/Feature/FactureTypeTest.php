<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class FactureTypeTest extends TestCase
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

    private function payload(int $clientId, ?int $produitId = null, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $clientId,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                [
                    'produit_id' => $produitId,
                    'designation' => 'Widget',
                    'quantite' => 3,
                    'prix_unitaire' => 100,
                    'tva_taux' => 20,
                    'remise_ligne' => 0,
                ],
            ],
        ], $overrides);
    }

    public function test_default_type_is_facture_with_fac_prefix(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id))->assertRedirect();

        $facture = Facture::latest('id')->first();
        $this->assertSame('facture', $facture->type->value);
        $this->assertStringStartsWith('FAC-', $facture->numero);
    }

    public function test_proforma_gets_its_own_pro_prefixed_sequence(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, null, ['type' => 'proforma']))->assertRedirect();

        $facture = Facture::latest('id')->first();
        $this->assertSame('proforma', $facture->type->value);
        $this->assertStringStartsWith('PRO-', $facture->numero);
    }

    public function test_facture_and_proforma_numbering_sequences_never_collide(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, null, ['type' => 'facture']));
        $this->post('/factures', $this->payload($client->id, null, ['type' => 'proforma']));
        $this->post('/factures', $this->payload($client->id, null, ['type' => 'facture']));

        $numeros = Facture::withoutGlobalScopes()->pluck('numero')->all();

        // Chaque séquence (FAC-/PRO-) s'incrémente indépendamment, jamais partagée.
        $facturesNumeros = collect($numeros)->filter(fn ($n) => str_starts_with($n, 'FAC-'))->values();
        $proformaNumeros = collect($numeros)->filter(fn ($n) => str_starts_with($n, 'PRO-'))->values();

        $this->assertCount(2, $facturesNumeros);
        $this->assertCount(1, $proformaNumeros);
        $this->assertStringEndsWith('0001', $facturesNumeros[0]);
        $this->assertStringEndsWith('0002', $facturesNumeros[1]);
        $this->assertStringEndsWith('0001', $proformaNumeros[0]);
    }

    public function test_proforma_never_consumes_stock(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client, $produit] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, $produit->id, ['type' => 'proforma']))->assertRedirect();

        $this->assertSame(50, $produit->fresh()->quantite_stock, 'Un proforma ne doit jamais faire bouger le stock.');
        $this->assertDatabaseCount('mouvements_stock', 0);
    }

    public function test_facture_still_consumes_stock_as_before(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client, $produit] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, $produit->id, ['type' => 'facture']))->assertRedirect();

        $this->assertSame(47, $produit->fresh()->quantite_stock);
        $this->assertDatabaseCount('mouvements_stock', 1);
    }

    public function test_deleting_a_proforma_does_not_attempt_to_restore_stock(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client, $produit] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, $produit->id, ['type' => 'proforma']));
        $facture = Facture::latest('id')->first();

        $this->delete("/factures/{$facture->id}")->assertRedirect();

        $this->assertSame(50, $produit->fresh()->quantite_stock);
        $this->assertDatabaseCount('mouvements_stock', 0);
    }

    public function test_type_is_immutable_after_creation(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client, $produit] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, $produit->id, ['type' => 'proforma']));
        $facture = Facture::latest('id')->first();

        $this->put("/factures/{$facture->id}", $this->payload($client->id, $produit->id, ['type' => 'facture']))
            ->assertRedirect();

        $this->assertSame('proforma', $facture->fresh()->type->value, 'Le type ne doit jamais pouvoir être changé après création, même via une requête forgée.');
    }

    public function test_duplicating_a_proforma_preserves_its_type(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client, $produit] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, $produit->id, ['type' => 'proforma']));
        $original = Facture::latest('id')->first();

        $this->post("/factures/{$original->id}/dupliquer")->assertRedirect();

        $copie = Facture::latest('id')->first();
        $this->assertNotSame($original->id, $copie->id);
        $this->assertSame('proforma', $copie->type->value);
        $this->assertStringStartsWith('PRO-', $copie->numero);
    }

    public function test_index_filters_by_type(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, null, ['type' => 'facture']));
        $this->post('/factures', $this->payload($client->id, null, ['type' => 'proforma']));

        $response = $this->get('/factures?type=proforma');

        $response->assertInertia(fn ($page) => $page
            ->component('Factures/Index')
            ->has('factures.data', 1)
            ->where('factures.data.0.type', 'proforma'));
    }

    public function test_pdf_meta_exposes_type_and_type_label(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutiqueId = $user->current_boutique_id;
        [$client] = $this->creerClientEtProduit($boutiqueId);
        $this->actingAs($user);

        $this->post('/factures', $this->payload($client->id, null, ['type' => 'proforma']));
        $facture = Facture::latest('id')->first();

        $apercu = app(\App\Support\FactureApercuBuilder::class)->construire($facture);

        $this->assertSame('proforma', $apercu['meta']['type']);
        $this->assertSame('Proforma', $apercu['meta']['type_label']);

        $this->get(route('factures.pdf', $facture))->assertOk();
    }
}
