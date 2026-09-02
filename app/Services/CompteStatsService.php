<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use Carbon\Carbon;

class CompteStatsService
{
    public function __construct(
        private readonly ClientSegmentationService $segmentation,
    ) {
    }

    public function pourCompte(User $user, ?string $debut = null, ?string $fin = null): array
    {
        $boutiques = Boutique::where('user_id', $user->id)->get();
        $boutiqueIds = $boutiques->pluck('id');

        [$debut, $fin] = $this->resoudrePeriode($debut, $fin);

        $clients = Client::withoutGlobalScopes()->whereIn('boutique_id', $boutiqueIds)->with('factures')->get();
        $segments = $this->segmentation->segmenter($clients);

        $facturesQuery = fn () => Facture::withoutGlobalScopes()->whereIn('boutique_id', $boutiqueIds);

        $chiffreAffaires = [
            'aujourdhui' => (float) $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])->whereDate('date_emission', now()->toDateString())->sum('total_ttc'),
            'cette_semaine' => (float) $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])->whereBetween('date_emission', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_ttc'),
            'ce_mois' => (float) $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])->whereYear('date_emission', now()->year)->whereMonth('date_emission', now()->month)->sum('total_ttc'),
            'cette_annee' => (float) $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])->whereYear('date_emission', now()->year)->sum('total_ttc'),
            'periode' => (float) $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])->whereBetween('date_emission', [$debut, $fin])->sum('total_ttc'),
        ];

        $depensesQuery = fn () => \App\Models\Depense::withoutGlobalScopes()->whereIn('boutique_id', $boutiqueIds);

        $depenses = [
            'aujourdhui' => (float) $depensesQuery()->whereDate('date_depense', now()->toDateString())->sum('montant'),
            'cette_semaine' => (float) $depensesQuery()->whereBetween('date_depense', [now()->startOfWeek(), now()->endOfWeek()])->sum('montant'),
            'ce_mois' => (float) $depensesQuery()->whereYear('date_depense', now()->year)->whereMonth('date_depense', now()->month)->sum('montant'),
            'cette_annee' => (float) $depensesQuery()->whereYear('date_depense', now()->year)->sum('montant'),
            'periode' => (float) $depensesQuery()->whereBetween('date_depense', [$debut, $fin])->sum('montant'),
        ];

        $caParBoutique = $boutiques->map(function (Boutique $boutique) {
            return [
                'boutique' => $boutique->only(['id', 'nom', 'slug']),
                'ca_mois' => (float) Facture::withoutGlobalScopes()
                    ->where('boutique_id', $boutique->id)
                    ->whereIn('statut', ['envoyee', 'payee'])
                    ->whereYear('date_emission', now()->year)
                    ->whereMonth('date_emission', now()->month)
                    ->sum('total_ttc'),
            ];
        })->sortByDesc('ca_mois')->values();

        return [
            'boutiques' => [
                'total' => $boutiques->count(),
                'actives' => $boutiques->where('statut', 'active')->count(),
                'suspendues' => $boutiques->where('statut', 'suspendue')->count(),
                'liste' => $boutiques,
            ],
            'clients' => [
                'total' => $clients->count(),
                'prospects' => $segments['prospect'],
                'nouveaux' => $segments['nouveau'],
                'actifs' => $segments['actif'],
                'reguliers' => $segments['regulier'],
            ],
            'chiffre_affaires' => $chiffreAffaires,
            'depenses' => $depenses,
            'resultat_periode' => $chiffreAffaires['periode'] - $depenses['periode'],
            'produits_en_rupture' => Produit::withoutGlobalScopes()->whereIn('boutique_id', $boutiqueIds)
                ->where('gere_stock', true)->whereNotNull('seuil_alerte')
                ->whereColumn('quantite_stock', '<=', 'seuil_alerte')->count(),
            'meilleure_boutique' => $caParBoutique->first(),
            'classement_boutiques' => $caParBoutique,
            'factures_recentes' => $facturesQuery()->with(['client:id,nom', 'boutique:id,nom'])->latest('date_emission')->limit(5)->get(),
            'periode' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],
        ];
    }

    private function resoudrePeriode(?string $debut, ?string $fin): array
    {
        if ($debut && $fin) {
            return [Carbon::parse($debut)->startOfDay(), Carbon::parse($fin)->endOfDay()];
        }

        return [now()->startOfMonth(), now()->endOfMonth()];
    }
}
