<?php

namespace App\Services;

use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FactureService
{
    public function __construct(
        private readonly FactureNumeroService $numeroService,
        private readonly StockService $stockService,
    ) {
    }

    public function creer(array $data, User $user, int $boutiqueId): Facture
    {
        return DB::transaction(function () use ($data, $user, $boutiqueId) {
            $dateEmission = $data['date_emission'];
            $annee = (int) date('Y', strtotime($dateEmission));

            $facture = Facture::create([
                'boutique_id' => $boutiqueId,
                'client_id' => $data['client_id'],
                'numero' => $this->numeroService->prochainNumero($boutiqueId, $annee),
                'statut' => $data['statut'] ?? 'brouillon',
                'modele_id' => $data['modele_id'] ?? 1,
                'date_emission' => $dateEmission,
                'date_echeance' => $data['date_echeance'] ?? null,
                'remise' => $data['remise'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->enregistrerLignes($facture, $data['lignes']);
            $this->consommerStockPourLignes($facture, $user->id);
            $this->recalculerTotaux($facture);

            return $facture->fresh('lignes');
        });
    }

    public function mettreAJour(Facture $facture, array $data, User $user): Facture
    {
        return DB::transaction(function () use ($facture, $data, $user) {
            $this->restituerStockPourFacture($facture, $user->id);
            $facture->lignes()->delete();

            $facture->update([
                'client_id' => $data['client_id'],
                'date_emission' => $data['date_emission'],
                'date_echeance' => $data['date_echeance'] ?? null,
                'remise' => $data['remise'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'modele_id' => $data['modele_id'] ?? $facture->modele_id->value,
            ]);

            $this->enregistrerLignes($facture, $data['lignes']);
            $this->consommerStockPourLignes($facture, $user->id);
            $this->recalculerTotaux($facture);

            return $facture->fresh('lignes');
        });
    }

    public function changerStatut(Facture $facture, string $statut, User $user): Facture
    {
        return DB::transaction(function () use ($facture, $statut, $user) {
            if ($statut === 'annulee' && $facture->statut !== 'annulee') {
                $this->restituerStockPourFacture($facture, $user->id);
            }

            $facture->update(['statut' => $statut]);

            return $facture->fresh('lignes');
        });
    }

    public function supprimer(Facture $facture, User $user): void
    {
        DB::transaction(function () use ($facture, $user) {
            if ($facture->statut !== 'annulee') {
                $this->restituerStockPourFacture($facture, $user->id);
            }

            $facture->lignes()->delete();
            $facture->delete();
        });
    }

    private function enregistrerLignes(Facture $facture, array $lignes): void
    {
        foreach ($lignes as $index => $ligne) {
            $quantite = (float) $ligne['quantite'];
            $prixUnitaire = (float) $ligne['prix_unitaire'];
            $tvaTaux = (float) ($ligne['tva_taux'] ?? 0);
            $remiseLigne = (float) ($ligne['remise_ligne'] ?? 0);

            $montantHt = ($quantite * $prixUnitaire) - $remiseLigne;
            $montantTva = $montantHt * ($tvaTaux / 100);
            $montantTtc = $montantHt + $montantTva;

            $facture->lignes()->create([
                'produit_id' => $ligne['produit_id'] ?? null,
                'designation' => $ligne['designation'],
                'description' => $ligne['description'] ?? null,
                'quantite' => $quantite,
                'prix_unitaire' => $prixUnitaire,
                'tva_taux' => $tvaTaux,
                'remise_ligne' => $remiseLigne,
                'montant_ht' => $montantHt,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'ordre' => $index,
            ]);
        }
    }

    private function consommerStockPourLignes(Facture $facture, int $userId): void
    {
        foreach ($facture->lignes as $ligne) {
            if (! $ligne->produit_id) {
                continue;
            }

            $produit = Produit::withoutGlobalScopes()->find($ligne->produit_id);

            if ($produit && $produit->gere_stock) {
                $this->stockService->enregistrerMouvement(
                    $produit,
                    'sortie',
                    (int) round($ligne->quantite),
                    "Facture {$facture->numero}",
                    $userId,
                    $facture->id,
                );
            }
        }
    }

    private function restituerStockPourFacture(Facture $facture, int $userId): void
    {
        foreach ($facture->lignes as $ligne) {
            if (! $ligne->produit_id) {
                continue;
            }

            $produit = Produit::withoutGlobalScopes()->find($ligne->produit_id);

            if ($produit && $produit->gere_stock) {
                $this->stockService->enregistrerMouvement(
                    $produit,
                    'entree',
                    (int) round($ligne->quantite),
                    "Annulation/modification facture {$facture->numero}",
                    $userId,
                    $facture->id,
                );
            }
        }
    }

    private function recalculerTotaux(Facture $facture): void
    {
        $facture->loadMissing('lignes');

        $sousTotal = $facture->lignes->sum('montant_ht');
        $totalTva = $facture->lignes->sum('montant_tva');
        $totalTtc = $sousTotal + $totalTva - $facture->remise;

        $facture->update([
            'sous_total' => $sousTotal,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
        ]);
    }
}
