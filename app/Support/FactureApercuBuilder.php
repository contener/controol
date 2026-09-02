<?php

namespace App\Support;

use App\Models\Facture;

/**
 * Construit la forme de données normalisée consommée à l'identique par les 10 modèles
 * de facture, côté serveur (PDF Blade, aperçu plein écran) — la même forme est
 * construite côté client par resources/js/Composables/useFactureApercu.js à partir de
 * l'état réactif du formulaire. Un seul jeu de noms de champs, jamais deux mappings.
 */
class FactureApercuBuilder
{
    public function construire(Facture $facture): array
    {
        $facture->loadMissing(['client', 'lignes', 'boutique']);
        $boutique = $facture->boutique;
        $client = $facture->client;

        return [
            'meta' => [
                'numero' => $facture->numero,
                'statut' => $facture->statut,
                'date_emission' => optional($facture->date_emission)->format('d/m/Y'),
                'date_echeance' => optional($facture->date_echeance)->format('d/m/Y'),
                'devise' => $boutique->devise,
            ],
            'boutique' => [
                'nom' => $boutique->nom,
                'logo_url' => $boutique->logo_path ? asset('storage/'.$boutique->logo_path) : null,
                'adresse' => $boutique->adresse,
                'ville' => $boutique->ville,
                'pays' => $boutique->pays,
                'telephone' => $boutique->telephone,
                'whatsapp' => $boutique->whatsapp,
                'email' => $boutique->email,
            ],
            'client' => [
                'nom' => $client->nom,
                'email' => $client->email,
                'telephone' => $client->telephone,
                'adresse' => $client->adresse,
                'ville' => $client->ville,
                'pays' => $client->pays,
                'numero_fiscal' => $client->numero_fiscal,
            ],
            'lignes' => $facture->lignes->map(fn ($ligne) => [
                'designation' => $ligne->designation,
                'description' => $ligne->description,
                'quantite' => (float) $ligne->quantite,
                'prix_unitaire' => (float) $ligne->prix_unitaire,
                'tva_taux' => (float) $ligne->tva_taux,
                'remise_ligne' => (float) $ligne->remise_ligne,
                'montant_ht' => (float) $ligne->montant_ht,
                'montant_tva' => (float) $ligne->montant_tva,
                'montant_ttc' => (float) $ligne->montant_ttc,
            ])->all(),
            'totaux' => [
                'sous_total' => (float) $facture->sous_total,
                'remise' => (float) $facture->remise,
                'total_tva' => (float) $facture->total_tva,
                'total_ttc' => (float) $facture->total_ttc,
            ],
            'notes' => $facture->notes,
        ];
    }
}
