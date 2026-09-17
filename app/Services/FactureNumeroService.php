<?php

namespace App\Services;

use App\Enums\TypeFacture;
use App\Models\CompteurFacture;
use Illuminate\Support\Facades\DB;

class FactureNumeroService
{
    /**
     * Génère le prochain numéro pour une boutique, de façon concurrency-safe (verrou de
     * ligne sur le compteur annuel de la boutique) — une séquence distincte par type
     * (FAC-/PRO-) : un proforma ne doit jamais consommer un numéro de la séquence légale
     * des factures, même s'il n'est jamais transformé en facture.
     */
    public function prochainNumero(int $boutiqueId, int $annee, TypeFacture $type = TypeFacture::Facture): string
    {
        return DB::transaction(function () use ($boutiqueId, $annee, $type) {
            $compteur = CompteurFacture::withoutGlobalScopes()
                ->where('boutique_id', $boutiqueId)
                ->where('annee', $annee)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if (! $compteur) {
                CompteurFacture::withoutGlobalScopes()->create([
                    'boutique_id' => $boutiqueId,
                    'annee' => $annee,
                    'type' => $type->value,
                    'dernier_numero' => 0,
                ]);
                $compteur = CompteurFacture::withoutGlobalScopes()
                    ->where('boutique_id', $boutiqueId)
                    ->where('annee', $annee)
                    ->where('type', $type->value)
                    ->lockForUpdate()
                    ->first();
            }

            $compteur->increment('dernier_numero');

            return sprintf('%s-%d-%04d', $type->prefixeNumero(), $annee, $compteur->dernier_numero);
        });
    }
}
