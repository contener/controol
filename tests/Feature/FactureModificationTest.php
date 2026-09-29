<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Produit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class FactureModificationTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function creerFacture($user, array $overrides = []): Facture
    {
        $boutiqueId = $user->current_boutique_id;
        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client Test', 'etiquette' => 'client']);

        $payload = array_merge([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['designation' => 'Prestation', 'quantite' => 1, 'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0],
            ],
        ], $overrides);

        $this->post('/factures', $payload);

        return Facture::withoutGlobalScopes()->where('boutique_id', $boutiqueId)->latest('id')->firstOrFail();
    }

    private function payloadModification(Facture $facture): array
    {
        return [
            'client_id' => $facture->client_id,
            'date_emission' => $facture->date_emission->toDateString(),
            'remise' => 0,
            'notes' => 'Corrigée',
            'lignes' => [
                ['designation' => 'Prestation corrigée', 'quantite' => 2, 'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0],
            ],
        ];
    }

    public function test_sent_invoice_can_be_edited_and_saved(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'envoyee']);

        $this->get(route('factures.edit', $facture))->assertOk();

        $this->put(route('factures.update', $facture), $this->payloadModification($facture))->assertRedirect();

        $this->assertSame('Corrigée', $facture->fresh()->notes);
        $this->assertSame('2000.00', (string) $facture->fresh()->total_ttc);
    }

    public function test_paid_invoice_can_be_edited_and_saved(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'payee']);

        $this->get(route('factures.edit', $facture))->assertOk();
        $this->put(route('factures.update', $facture), $this->payloadModification($facture))->assertRedirect();

        $this->assertSame('Corrigée', $facture->fresh()->notes);
    }

    public function test_cancelled_invoice_cannot_be_edited(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'annulee']);

        $this->get(route('factures.edit', $facture))->assertStatus(403);
        $this->put(route('factures.update', $facture), $this->payloadModification($facture))->assertStatus(403);

        $this->assertNotSame('Corrigée', $facture->fresh()->notes);
    }

    public function test_editing_never_changes_the_immutable_number_or_type(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'envoyee']);
        $numeroOriginal = $facture->numero;

        $this->put(route('factures.update', $facture), $this->payloadModification($facture));

        $this->assertSame($numeroOriginal, $facture->fresh()->numero);
        $this->assertSame('facture', $facture->fresh()->type->value);
    }

    public function test_recently_created_invoice_cannot_be_deleted_regardless_of_status(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'payee']);

        $this->delete(route('factures.destroy', $facture))->assertStatus(403);
        $this->assertDatabaseHas('factures', ['id' => $facture->id]);
    }

    public function test_invoice_older_than_14_days_can_be_deleted_even_if_paid(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'payee']);
        $facture->forceFill(['created_at' => now()->subDays(15)])->save();

        $this->delete(route('factures.destroy', $facture))->assertRedirect();

        $this->assertDatabaseMissing('factures', ['id' => $facture->id]);
    }

    public function test_invoice_exactly_14_days_old_can_be_deleted(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->forceFill(['created_at' => now()->subDays(14)])->save();

        $this->delete(route('factures.destroy', $facture))->assertRedirect();

        $this->assertDatabaseMissing('factures', ['id' => $facture->id]);
    }

    public function test_deleting_an_old_stock_managed_invoice_restores_stock(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutiqueId = $user->current_boutique_id;
        $produit = Produit::create([
            'boutique_id' => $boutiqueId, 'type' => 'produit', 'nom' => 'Widget',
            'prix_vente' => 1000, 'unite' => 'pièce', 'gere_stock' => true, 'quantite_stock' => 10,
        ]);
        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client Test', 'etiquette' => 'client']);

        $this->actingAs($user);
        $this->post('/factures', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['produit_id' => $produit->id, 'designation' => 'Widget', 'quantite' => 3, 'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0],
            ],
        ]);
        $facture = Facture::withoutGlobalScopes()->where('boutique_id', $boutiqueId)->latest('id')->firstOrFail();
        $this->assertSame(7, $produit->fresh()->quantite_stock);

        $facture->forceFill(['created_at' => now()->subDays(15)])->save();
        $this->delete(route('factures.destroy', $facture))->assertRedirect();

        $this->assertSame(10, $produit->fresh()->quantite_stock);
    }

    // Régression : restituerStockPourFacture() (appelée avant la suppression des
    // lignes) mettait en cache l'ancienne collection lignes sur l'instance -- sans
    // rechargement explicite, consommerStockPourLignes() et recalculerTotaux()
    // opéraient ensuite sur ces lignes obsolètes (déjà supprimées en base), au lieu
    // des nouvelles. Le stock ne reflétait jamais la quantité modifiée, et le total
    // affiché restait l'ancien montant.
    public function test_editing_line_quantities_correctly_adjusts_stock_and_totals(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $boutiqueId = $user->current_boutique_id;
        $produit = Produit::create([
            'boutique_id' => $boutiqueId, 'type' => 'produit', 'nom' => 'Widget',
            'prix_vente' => 1000, 'unite' => 'pièce', 'gere_stock' => true, 'quantite_stock' => 10,
        ]);
        $client = Client::create(['boutique_id' => $boutiqueId, 'nom' => 'Client Test', 'etiquette' => 'client']);

        $this->actingAs($user);
        $this->post('/factures', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['produit_id' => $produit->id, 'designation' => 'Widget', 'quantite' => 3, 'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0],
            ],
        ]);
        $facture = Facture::withoutGlobalScopes()->where('boutique_id', $boutiqueId)->latest('id')->firstOrFail();
        $this->assertSame(7, $produit->fresh()->quantite_stock);
        $this->assertSame('3000.00', (string) $facture->fresh()->total_ttc);

        $facture->update(['statut' => 'envoyee']);

        // 3 -> 5 : doit restituer les 3 initiales puis n'en consommer que 5 (net -5
        // depuis le stock d'origine de 10), jamais -3 (bug) ni -8 (double comptage).
        $this->put(route('factures.update', $facture), [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['produit_id' => $produit->id, 'designation' => 'Widget', 'quantite' => 5, 'prix_unitaire' => 1000, 'tva_taux' => 0, 'remise_ligne' => 0],
            ],
        ])->assertRedirect();

        $this->assertSame(5, $produit->fresh()->quantite_stock);
        $this->assertSame('5000.00', (string) $facture->fresh()->total_ttc);
        $this->assertSame(1, $facture->fresh()->lignes()->count());
        $this->assertSame('5.00', (string) $facture->fresh()->lignes->first()->quantite);
    }

    public function test_index_and_show_expose_the_computed_flags(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);
        $facture = $this->creerFacture($user);
        $facture->update(['statut' => 'envoyee']);
        $facture->forceFill(['created_at' => now()->subDays(15)])->save();

        $this->get(route('factures.index'))->assertInertia(fn ($page) => $page
            ->where('factures.data.0.est_modifiable', true)
            ->where('factures.data.0.est_supprimable', true));

        $this->get(route('factures.show', $facture))->assertInertia(fn ($page) => $page
            ->where('facture.est_modifiable', true)
            ->where('facture.est_supprimable', true));
    }

    // BelongsToBoutique filtre déjà la facture hors de la portée de $intrus avant même
    // que la policy n'intervienne (voir ConversationTest pour le même principe) : le
    // modèle est introuvable pour lui, pas seulement interdit -- 404, pas 403.
    public function test_user_cannot_edit_or_delete_another_users_invoice(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($proprietaire);
        $facture = $this->creerFacture($proprietaire);
        $facture->forceFill(['created_at' => now()->subDays(15)])->save();

        $intrus = $this->creerUtilisateurAvecBoutique();

        $this->actingAs($intrus)->get(route('factures.edit', $facture))->assertNotFound();
        $this->actingAs($intrus)->delete(route('factures.destroy', $facture))->assertNotFound();
        $this->assertDatabaseHas('factures', ['id' => $facture->id]);
    }
}
