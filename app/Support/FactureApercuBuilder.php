<?php

namespace App\Support;

use App\Models\Boutique;
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
                'type' => $facture->type->value,
                'type_label' => $facture->type->label(),
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
                'code_postal' => $client->code_postal,
                'numero_fiscal' => $client->numero_fiscal,
                'rccm' => $client->rccm,
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
                // Propres aux Reçus -- présents (mais sans effet visuel, les modèles ne
                // les affichent que si meta.type === 'recu') pour les autres types.
                'montant_avance' => $facture->montant_avance !== null ? (float) $facture->montant_avance : null,
                'reste_a_payer' => $facture->resteAPayer(),
            ],
            'notes' => $facture->notes,
            'garantie' => $facture->garantie,
        ];
    }

    /**
     * Aperçu d'exemple d'un modèle (galerie de modèles de l'app mobile) : l'en-tête
     * réel de la boutique (nom, logo, coordonnées, NUI, note de bas de facture) avec
     * un client et des lignes fictifs -- mêmes valeurs que exempleApercu.js côté web.
     */
    public function exemple(Boutique $boutique): array
    {
        return [
            'meta' => [
                'numero' => 'FAC-'.now()->year.'-0001',
                'type' => 'facture',
                'type_label' => 'Facture',
                'statut' => 'brouillon',
                'date_emission' => now()->format('d/m/Y'),
                'date_echeance' => now()->addDays(14)->format('d/m/Y'),
                'devise' => $boutique->devise ?: 'XAF',
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
                'nom' => 'Société Alpha SARL',
                'email' => 'contact@alpha.cm',
                'telephone' => '+237 6 11 11 11 11',
                'adresse' => null,
                'ville' => 'Douala',
                'pays' => 'Cameroun',
                'code_postal' => null,
                'numero_fiscal' => 'M022312345678B',
                'rccm' => 'RC/DLA/2024/B/1234',
            ],
            'lignes' => [
                ['designation' => 'Ordinateur portable 15"', 'description' => null, 'quantite' => 2.0, 'prix_unitaire' => 350000.0, 'tva_taux' => 19.25, 'remise_ligne' => 0.0, 'montant_ht' => 700000.0, 'montant_tva' => 134750.0, 'montant_ttc' => 834750.0],
                ['designation' => 'Installation et configuration', 'description' => null, 'quantite' => 1.0, 'prix_unitaire' => 25000.0, 'tva_taux' => 19.25, 'remise_ligne' => 0.0, 'montant_ht' => 25000.0, 'montant_tva' => 4812.5, 'montant_ttc' => 29812.5],
            ],
            'totaux' => [
                'sous_total' => 725000.0,
                'remise' => 0.0,
                'total_tva' => 139562.5,
                'total_ttc' => 864562.5,
                'montant_avance' => null,
                'reste_a_payer' => null,
            ],
            'notes' => 'Merci de votre confiance.',
            'garantie' => "Garantie de 6 mois. Ne couvre pas les dommages liés à l'eau, aux chocs ou à une mauvaise utilisation.",
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
