<?php

namespace App\Support;

/**
 * Détection floue des colonnes d'un fichier Excel de contacts — les en-têtes arrivent déjà
 * normalisés en snake_case par WithHeadingRow (voir App\Imports\ContactsImportReader), donc
 * cette table ne liste que des variantes elles-mêmes en snake_case.
 */
class ContactColonnesExcel
{
    private const ALIAS = [
        'nom' => ['nom', 'nom_complet', 'full_name', 'name', 'client', 'contact', 'nom_du_contact', 'prenom_nom'],
        'telephone' => ['telephone', 'phone', 'mobile', 'numero_de_telephone', 'tel', 'num_tel'],
        'whatsapp' => ['whatsapp', 'numero_whatsapp', 'whats_app', 'num_whatsapp'],
        'email' => ['email', 'e_mail', 'mail', 'adresse_email'],
        'ville' => ['ville', 'city'],
        'entreprise' => ['entreprise', 'company', 'boutique', 'societe'],
        'categorie' => ['categorie', 'category'],
        'source' => ['source'],
        'notes' => ['notes', 'note', 'remarque', 'commentaire'],
    ];

    /**
     * @param  string[]  $enTetes  En-têtes réellement présentes dans le fichier (snake_case).
     * @return array<string, string|null> Champ CONTROOL => colonne détectée (ou null).
     */
    public function detecter(array $enTetes): array
    {
        $mapping = [];

        foreach (self::ALIAS as $champ => $variantes) {
            $mapping[$champ] = null;
            foreach ($variantes as $variante) {
                if (in_array($variante, $enTetes, true)) {
                    $mapping[$champ] = $variante;
                    break;
                }
            }
        }

        return $mapping;
    }

    public static function champs(): array
    {
        return array_keys(self::ALIAS);
    }
}
