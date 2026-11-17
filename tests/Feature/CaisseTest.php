<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Depense;
use App\Models\Facture;
use App\Models\MouvementCaisse;
use App\Models\User;
use App\Services\CaisseService;
use App\Services\FactureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Caisse de boutique (2026-11-17) : ajouts/retraits manuels, ventes encaissées,
 * dépenses, corrections en grand livre -- voir CaisseService.
 */
class CaisseTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function solde(User $user): float
    {
        return app(CaisseService::class)->solde($user->current_boutique_id);
    }

    private function facture(User $user, array $donnees = []): Facture
    {
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client', 'etiquette' => 'client']);

        return app(FactureService::class)->creer(array_merge([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'statut' => 'payee',
            'remise' => 0,
            'lignes' => [['designation' => 'Article', 'quantite' => 2, 'prix_unitaire' => 5000, 'tva_taux' => 0, 'remise_ligne' => 0]],
        ], $donnees), $user, $user->current_boutique_id);
    }

    public function test_ajout_et_retrait_manuels(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')->postJson('/api/caisse/apport', ['montant' => 20000, 'motif' => 'Fonds de caisse'])
            ->assertCreated()->assertJsonPath('solde', 20000);
        $this->actingAs($user, 'sanctum')->postJson('/api/caisse/retrait', ['montant' => 5000, 'motif' => 'Banque'])
            ->assertCreated()->assertJsonPath('solde', 15000);

        // Retrait supérieur au solde refusé.
        $this->actingAs($user, 'sanctum')->postJson('/api/caisse/retrait', ['montant' => 15001, 'motif' => 'Trop'])
            ->assertUnprocessable()->assertJsonValidationErrors('montant');
        $this->assertSame(15000.0, $this->solde($user));
    }

    public function test_vente_payee_augmente_la_caisse_et_annulation_la_corrige(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $facture = $this->facture($user);

        $this->assertSame(10000.0, $this->solde($user));

        // Re-synchroniser ne crée jamais de doublon.
        app(CaisseService::class)->synchroniserFacture($facture, $user->id);
        $this->assertSame(1, MouvementCaisse::where('facture_id', $facture->id)->count());

        app(FactureService::class)->changerStatut($facture, 'annulee', $user);
        $this->assertSame(0.0, $this->solde($user));
        $this->assertSame(2, MouvementCaisse::where('facture_id', $facture->id)->count());
    }

    public function test_facture_impayee_puis_payee(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $facture = $this->facture($user, ['statut' => 'envoyee']);
        $this->assertSame(0.0, $this->solde($user));

        app(FactureService::class)->changerStatut($facture, 'payee', $user);
        $this->assertSame(10000.0, $this->solde($user));
    }

    public function test_recu_avance_puis_solde(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $recu = $this->facture($user, ['type' => 'recu', 'statut' => 'envoyee', 'montant_avance' => 3000]);
        $this->assertSame(3000.0, $this->solde($user));

        app(FactureService::class)->changerStatut($recu, 'payee', $user);
        $this->assertSame(10000.0, $this->solde($user));
    }

    public function test_proforma_et_brouillon_ne_touchent_pas_la_caisse(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $this->facture($user, ['type' => 'proforma']);
        $this->facture($user, ['statut' => 'brouillon']);

        $this->assertSame(0.0, $this->solde($user));
    }

    public function test_modification_et_suppression_d_une_vente_payee(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $facture = $this->facture($user);

        app(FactureService::class)->mettreAJour($facture, [
            'client_id' => $facture->client_id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [['designation' => 'Article', 'quantite' => 1, 'prix_unitaire' => 5000, 'tva_taux' => 0, 'remise_ligne' => 0]],
        ], $user);
        $this->assertSame(5000.0, $this->solde($user));

        app(FactureService::class)->supprimer($facture->fresh(), $user);
        $this->assertSame(0.0, $this->solde($user));
        // Le journal garde l'historique, lien vers la facture remis à null.
        $this->assertSame(3, MouvementCaisse::whereNull('facture_id')->count());
    }

    public function test_depense_reduit_la_caisse_et_ses_corrections(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $this->actingAs($user);
        app(CaisseService::class)->apport($user->currentBoutique, $user, 10000, 'Fonds');

        $depense = Depense::create(['boutique_id' => $user->current_boutique_id, 'categorie' => 'Loyer', 'montant' => 4000, 'date_depense' => now()->toDateString(), 'created_by' => $user->id]);
        $this->assertSame(6000.0, $this->solde($user));

        $depense->update(['montant' => 5000]);
        $this->assertSame(5000.0, $this->solde($user));

        $depense->update(['montant' => 1000]);
        $this->assertSame(9000.0, $this->solde($user));

        $depense->delete();
        $this->assertSame(10000.0, $this->solde($user));
    }

    public function test_documents_anterieurs_a_l_ouverture_ignores(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $this->actingAs($user);
        $depense = Depense::create(['boutique_id' => $user->current_boutique_id, 'categorie' => 'Loyer', 'montant' => 4000, 'date_depense' => now()->toDateString(), 'created_by' => $user->id]);
        MouvementCaisse::query()->delete();

        // Caisse ouverte après la création de la dépense : la modifier ne crée aucune correction.
        $user->currentBoutique->forceFill(['caisse_ouverte_le' => now()->addMinute()])->save();
        $depense->update(['montant' => 1000]);

        $this->assertSame(0, MouvementCaisse::count());
    }

    public function test_acces_reserve_a_la_permission_depenses(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $proprietaire->currentBoutique->collaborateurs()->attach($collaborateur->id, ['acces_depenses' => false]);
        $collaborateur->forceFill(['current_boutique_id' => $proprietaire->current_boutique_id])->save();

        $this->actingAs($collaborateur, 'sanctum')->getJson('/api/caisse')->assertForbidden();
        $this->actingAs($collaborateur, 'sanctum')->postJson('/api/caisse/apport', ['montant' => 100, 'motif' => 'X'])->assertForbidden();

        $this->actingAs($proprietaire, 'sanctum')->getJson('/api/caisse')->assertOk()->assertJsonPath('solde', 0);
        $this->actingAs($proprietaire)->get('/caisse')->assertOk();
    }
}
