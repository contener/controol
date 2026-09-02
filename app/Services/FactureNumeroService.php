<?php

namespace App\Services;

use App\Models\CompteurFacture;
use Illuminate\Support\Facades\DB;

class FactureNumeroService
{
    /**
     * Génère le prochain numéro de facture pour une boutique, de façon concurrency-safe
     * (verrou de ligne sur le compteur annuel de la boutique).
     */
    public function prochainNumero(int $boutiqueId, int $annee): string
    {
        return DB::transaction(function () use ($boutiqueId, $annee) {
            $compteur = CompteurFacture::withoutGlobalScopes()
                ->where('boutique_id', $boutiqueId)
                ->where('annee', $annee)
                ->lockForUpdate()
                ->first();

            if (! $compteur) {
                $compteur = CompteurFacture::withoutGlobalScopes()->create([
                    'boutique_id' => $boutiqueId,
                    'annee' => $annee,
                    'dernier_numero' => 0,
                ]);
                $compteur = CompteurFacture::withoutGlobalScopes()
                    ->where('boutique_id', $boutiqueId)
                    ->where('annee', $annee)
                    ->lockForUpdate()
                    ->first();
            }

            $compteur->increment('dernier_numero');

            return sprintf('FAC-%d-%04d', $annee, $compteur->dernier_numero);
        });
    }
}
