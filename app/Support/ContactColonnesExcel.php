<?php

namespace App\Support;

/**
 * Détection des colonnes d'un fichier Excel/CSV de contacts — les en-têtes arrivent déjà
 * normalisées en snake_case par WithHeadingRow (voir App\Imports\ContactsImportReader).
 *
 * Deux familles de colonnes :
 * - Les champs simples (nom, ville, entreprise...) : une seule colonne les porte, détectée
 *   par une liste de variantes connues (dont les intitulés Google Contacts : first_name,
 *   organization_name, address_1_city...).
 * - Les champs répétés par Google Contacts (téléphones, e-mails) : le nombre de colonnes varie
 *   d'un export à l'autre (phone_1_value, phone_2_value, ... avec un phone_N_type optionnel) —
 *   une liste figée ne peut pas les couvrir, il faut les détecter par motif.
 */
class ContactColonnesExcel
{
    // 'email' n'apparaît volontairement pas ici : comme pour les téléphones, les colonnes
    // e-mail sont détectées automatiquement via colonnesEmails() (simples ET numérotées à la
    // Google Contacts) — un mapping "simple" séparé serait sans effet sur l'extraction réelle
    // et donc trompeur dans l'UI d'ajustement des colonnes.
    private const ALIAS_SIMPLES = [
        'nom' => ['nom', 'nom_complet', 'full_name', 'name', 'client', 'contact', 'nom_du_contact', 'prenom_nom'],
        'prenom' => ['prenom', 'first_name', 'given_name'],
        'nom_famille' => ['nom_famille', 'last_name', 'family_name', 'surname'],
        'ville' => ['ville', 'city', 'address_1_city', 'address_1_town'],
        'entreprise' => ['entreprise', 'company', 'boutique', 'societe', 'organization_name'],
        'poste' => ['poste', 'job_title', 'title', 'organization_title'],
        'categorie' => ['categorie', 'category', 'group', 'groups'],
        'source' => ['source'],
        'notes' => ['notes', 'note', 'remarque', 'commentaire'],
        'adresse' => ['adresse', 'address', 'address_1_formatted', 'address_1_street'],
        'region' => ['region', 'address_1_region', 'state'],
        'pays' => ['pays', 'country', 'address_1_country'],
        'code_postal' => ['code_postal', 'postal_code', 'zip', 'address_1_postal_code'],
        'date_anniversaire' => ['date_anniversaire', 'anniversaire', 'birthday'],
    ];

    private const ALIAS_TELEPHONE_SIMPLE = ['telephone', 'phone', 'mobile', 'numero_de_telephone', 'tel', 'num_tel'];

    private const ALIAS_WHATSAPP_SIMPLE = ['whatsapp', 'numero_whatsapp', 'whats_app', 'num_whatsapp'];

    /**
     * @param  string[]  $enTetes
     * @return array<string, string|null> Champ CONTROOL => colonne détectée (ou null).
     */
    public function detecterSimples(array $enTetes): array
    {
        $mapping = [];

        foreach (self::ALIAS_SIMPLES as $champ => $variantes) {
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

    /**
     * Colonnes de téléphone : les colonnes numérotées à la Google Contacts (phone_1_value,
     * phone_2_value...) en plus des alias simples classiques. Le nom de la colonne "type"
     * associée (phone_1_type) est renvoyé quand elle existe, pour repérer un numéro
     * explicitement étiqueté WhatsApp.
     *
     * @return array<int, array{valeur: string, type: ?string}>
     */
    public function colonnesTelephones(array $enTetes): array
    {
        $colonnes = [];

        foreach ($enTetes as $entete) {
            if (preg_match('/^(?:phone|telephone)_?(\d+)_?value$/', $entete, $m)) {
                $indice = $m[1];
                $typeCol = null;
                foreach (["phone_{$indice}_type", "telephone_{$indice}_type"] as $candidat) {
                    if (in_array($candidat, $enTetes, true)) {
                        $typeCol = $candidat;
                        break;
                    }
                }
                $colonnes[] = ['valeur' => $entete, 'type' => $typeCol];
            }
        }

        foreach (self::ALIAS_TELEPHONE_SIMPLE as $alias) {
            if (in_array($alias, $enTetes, true)) {
                $colonnes[] = ['valeur' => $alias, 'type' => null];
            }
        }

        return $colonnes;
    }

    public function colonneWhatsappExplicite(array $enTetes): ?string
    {
        foreach (self::ALIAS_WHATSAPP_SIMPLE as $alias) {
            if (in_array($alias, $enTetes, true)) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function colonnesEmails(array $enTetes): array
    {
        $colonnes = [];

        foreach ($enTetes as $entete) {
            if (preg_match('/^e_?mail_?\d+_?value$/', $entete)) {
                $colonnes[] = $entete;
            }
        }

        foreach (['email', 'e_mail', 'mail', 'adresse_email'] as $alias) {
            if (in_array($alias, $enTetes, true)) {
                $colonnes[] = $alias;
            }
        }

        return array_values(array_unique($colonnes));
    }

    public static function champsSimples(): array
    {
        return array_keys(self::ALIAS_SIMPLES);
    }
}
