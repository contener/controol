<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lecteur brut d'un fichier Excel/CSV de contacts — WithHeadingRow normalise chaque en-tête
 * en snake_case (« Nom complet » → nom_complet, « WhatsApp » → whatsapp), ce qui permet la
 * détection de colonnes par correspondance floue dans ContactImportController sans avoir à
 * gérer la casse/les accents nous-mêmes. Le délimiteur est déterminé une seule fois, en amont
 * (voir PreparationFichierCsvService), avant même la création de ce lecteur — jamais deviné à
 * nouveau ici.
 */
class ContactsImportReader implements ToCollection, WithCustomCsvSettings, WithHeadingRow
{
    public Collection $lignes;

    public function __construct(private readonly string $delimiteur = ',') {}

    public function collection(Collection $lignes): void
    {
        $this->lignes = $lignes;
    }

    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->delimiteur];
    }
}
