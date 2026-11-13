<?php

namespace App\Support;

/**
 * Règles de validation des champs d'une boutique -- seule définition, partagée par
 * StoreBoutiqueRequest / UpdateBoutiqueRequest (création et modification depuis
 * "Mes boutiques") et par CreateNewUser (formulaire d'inscription en étapes, qui
 * crée la première boutique, 2026-11-13). Logo et bannière sont compressés avant
 * validation par l'appelant (voir CompresseImagesTeleversees / CreateNewUser).
 */
class ReglesBoutique
{
    public const TAILLE_LOGO_KO = 2048;

    public const TAILLE_BANNIERE_KO = 4096;

    /**
     * @param  string  $prefixe  ex. "boutique." quand les champs sont imbriqués
     *                           (formulaire d'inscription).
     */
    public static function champs(string $prefixe = ''): array
    {
        $regles = [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categorie' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'devise' => ['nullable', 'string', 'max:3'],
            'taux_tva_defaut' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nui' => ['nullable', 'string', 'max:50'],
            'note_pied_facture' => ['nullable', 'string', 'max:1000'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:'.self::TAILLE_LOGO_KO],
            'banniere' => ['nullable', 'image', 'max:'.self::TAILLE_BANNIERE_KO],
        ];

        if ($prefixe === '') {
            return $regles;
        }

        $prefixees = [];
        foreach ($regles as $champ => $regle) {
            $prefixees[$prefixe.$champ] = $regle;
        }

        return $prefixees;
    }

    /** Libellés français des champs, pour des messages d'erreur lisibles. */
    public static function libelles(string $prefixe = ''): array
    {
        $libelles = [
            'nom' => 'nom de la boutique',
            'categorie' => 'catégorie',
            'adresse' => 'adresse',
            'ville' => 'ville',
            'pays' => 'pays',
            'telephone' => 'téléphone de la boutique',
            'whatsapp' => 'numéro WhatsApp',
            'email' => 'e-mail de la boutique',
            'devise' => 'devise',
            'taux_tva_defaut' => 'taux de TVA par défaut',
            'nui' => 'NUI',
            'note_pied_facture' => 'note de bas de facture',
            'facebook_url' => 'lien Facebook',
            'instagram_url' => 'lien Instagram',
            'telegram_url' => 'lien Telegram',
            'logo' => 'logo',
            'banniere' => 'bannière',
            'description' => 'description',
        ];

        $resultat = [];
        foreach ($libelles as $champ => $libelle) {
            $resultat[$prefixe.$champ] = $libelle;
        }

        return $resultat;
    }
}
