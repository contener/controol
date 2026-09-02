<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;

class ClientSegmentationService
{
    /**
     * Segmente les clients d'une liste (déjà chargée avec leurs factures) selon leur historique d'achat.
     * Ne modifie jamais la colonne `etiquette` (prospect/client) : c'est une classification dérivée en plus.
     */
    public function segmenter(Collection $clients): array
    {
        $compteurs = ['prospect' => 0, 'nouveau' => 0, 'actif' => 0, 'regulier' => 0];

        foreach ($clients as $client) {
            $compteurs[$this->segment($client)]++;
        }

        return $compteurs;
    }

    public function segment(Client $client): string
    {
        if ($client->etiquette === 'prospect') {
            return 'prospect';
        }

        $facturesPayees = $client->factures->whereIn('statut', ['envoyee', 'payee']);

        if ($facturesPayees->count() >= 3) {
            return 'regulier';
        }

        $derniereFacture = $facturesPayees->sortByDesc('date_emission')->first();

        if ($derniereFacture && $derniereFacture->date_emission->gte(now()->subDays(90))) {
            return 'actif';
        }

        return 'nouveau';
    }
}
