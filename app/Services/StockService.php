<?php

namespace App\Services;

use App\Models\Produit;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Enregistre un mouvement de stock et met à jour la quantité du produit de façon atomique.
     */
    public function enregistrerMouvement(
        Produit $produit,
        string $type,
        int $quantite,
        ?string $motif = null,
        ?int $userId = null,
        ?int $factureId = null,
    ): void {
        DB::transaction(function () use ($produit, $type, $quantite, $motif, $userId, $factureId) {
            $produit = Produit::withoutGlobalScopes()->lockForUpdate()->findOrFail($produit->id);

            $quantiteAvant = $produit->quantite_stock;
            $quantiteApres = match ($type) {
                'entree' => $quantiteAvant + $quantite,
                'sortie' => $quantiteAvant - $quantite,
                'ajustement' => $quantite,
                default => $quantiteAvant,
            };

            $produit->quantite_stock = $quantiteApres;
            $produit->save();

            $produit->mouvementsStock()->create([
                'boutique_id' => $produit->boutique_id,
                'type' => $type,
                'quantite' => $quantite,
                'quantite_avant' => $quantiteAvant,
                'quantite_apres' => $quantiteApres,
                'motif' => $motif,
                'user_id' => $userId,
                'facture_id' => $factureId,
            ]);
        });
    }
}
