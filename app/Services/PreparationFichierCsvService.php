<?php

namespace App\Services;

/**
 * Prépare un fichier CSV importé avant lecture : encodage et séparateur ne sont pas garantis
 * (un export Google Contacts peut être en UTF-8 avec BOM, Windows-1252, ou utiliser un
 * point-virgule selon la locale) — appelée une seule fois avant stockage du fichier temporaire,
 * pour que la lecture (aperçu et confirmation) se fasse toujours sur un contenu propre et
 * connu, sans avoir à redétecter à chaque étape.
 */
class PreparationFichierCsvService
{
    private const ENCODAGES_CANDIDATS = ['UTF-8', 'Windows-1252', 'ISO-8859-1'];

    public function normaliserEnUtf8(string $contenu): string
    {
        if (str_starts_with($contenu, "\xEF\xBB\xBF")) {
            $contenu = substr($contenu, 3);
        }

        if ($contenu === '' || mb_check_encoding($contenu, 'UTF-8')) {
            return $contenu;
        }

        $encodageDetecte = mb_detect_encoding($contenu, self::ENCODAGES_CANDIDATS, true) ?: 'Windows-1252';

        return mb_convert_encoding($contenu, 'UTF-8', $encodageDetecte);
    }

    public function detecterDelimiteur(string $contenu): string
    {
        $premiereLigne = strtok($contenu, "\n") ?: '';

        $candidats = [
            ',' => substr_count($premiereLigne, ','),
            ';' => substr_count($premiereLigne, ';'),
            "\t" => substr_count($premiereLigne, "\t"),
        ];
        arsort($candidats);
        $meilleur = array_key_first($candidats);

        return $candidats[$meilleur] > 0 ? $meilleur : ',';
    }
}
