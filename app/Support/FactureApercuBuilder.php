<?php

namespace App\Support;

use App\Models\Facture;
use Illuminate\Support\Facades\Storage;

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
                'logo_url' => $this->logoDataUri($boutique->logo_path),
                'adresse' => $boutique->adresse,
                'ville' => $boutique->ville,
                'pays' => $boutique->pays,
                'telephone' => $boutique->telephone,
                'whatsapp' => $boutique->whatsapp,
                'email' => $boutique->email,
                'nui' => $boutique->nui,
                'note_pied_facture' => $boutique->note_pied_facture,
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
            'garantie' => $facture->garantie,
        ];
    }

    /**
     * dompdf refuse par défaut les images distantes (enable_remote = false, non modifié
     * dans cette app) : une URL http(s) vers le logo ne s'affiche donc jamais dans le PDF
     * généré, même si elle fonctionne très bien dans l'aperçu navigateur. On embarque
     * directement le fichier en data URI pour que le logo s'affiche de façon fiable, sans
     * dépendre de cette option ni d'un accès réseau au moment de la génération.
     */
    private function logoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
        $contenu = Storage::disk('public')->get($logoPath);

        return 'data:'.$mime.';base64,'.base64_encode($contenu);
    }
}
