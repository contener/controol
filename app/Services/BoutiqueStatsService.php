<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Produit;

class BoutiqueStatsService
{
    public function __construct(
        private readonly ClientSegmentationService $segmentation,
    ) {
    }

    public function pourBoutique(Boutique $boutique): array
    {
        $clients = Client::withoutGlobalScopes()->where('boutique_id', $boutique->id)->with('factures')->get();
        $segments = $this->segmentation->segmenter($clients);

        $produits = Produit::withoutGlobalScopes()->where('boutique_id', $boutique->id);

        $facturesQuery = fn () => Facture::withoutGlobalScopes()->where('boutique_id', $boutique->id);

        $caMois = $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])
            ->whereYear('date_emission', now()->year)
            ->whereMonth('date_emission', now()->month)
            ->sum('total_ttc');

        $depensesMois = $boutique->depenses()
            ->whereYear('date_depense', now()->year)
            ->whereMonth('date_depense', now()->month)
            ->sum('montant');

        $produitsVendus = FactureLigne::query()
            ->join('factures', 'factures.id', '=', 'facture_lignes.facture_id')
            ->where('factures.boutique_id', $boutique->id)
            ->whereIn('factures.statut', ['envoyee', 'payee'])
            ->whereNotNull('facture_lignes.produit_id')
            ->selectRaw('facture_lignes.produit_id, SUM(facture_lignes.quantite) as total_quantite')
            ->groupBy('facture_lignes.produit_id')
            ->orderByDesc('total_quantite')
            ->limit(5)
            ->with('produit:id,nom')
            ->get();

        return [
            'clients' => [
                'total' => $clients->count(),
                'prospects' => $segments['prospect'],
                'nouveaux' => $segments['nouveau'],
                'actifs' => $segments['actif'],
                'reguliers' => $segments['regulier'],
            ],
            'produits' => [
                'total' => (clone $produits)->count(),
                'actifs' => (clone $produits)->where('actif', true)->count(),
                'epuises' => (clone $produits)->where('gere_stock', true)->where('quantite_stock', '<=', 0)->count(),
                'proches_rupture' => (clone $produits)->where('gere_stock', true)->whereNotNull('seuil_alerte')
                    ->whereColumn('quantite_stock', '<=', 'seuil_alerte')->where('quantite_stock', '>', 0)->count(),
            ],
            'factures' => [
                'total' => $facturesQuery()->count(),
                'aujourdhui' => $facturesQuery()->whereDate('date_emission', now()->toDateString())->count(),
                'ce_mois' => $facturesQuery()->whereYear('date_emission', now()->year)->whereMonth('date_emission', now()->month)->count(),
                'impayees' => $facturesQuery()->where('statut', 'envoyee')->count(),
                'payees' => $facturesQuery()->where('statut', 'payee')->count(),
            ],
            'finances' => [
                'ca_mois' => (float) $caMois,
                'depenses_mois' => (float) $depensesMois,
                'resultat_mois' => (float) $caMois - (float) $depensesMois,
            ],
            'produits_plus_vendus' => $produitsVendus,
            'factures_recentes' => $facturesQuery()->with('client:id,nom')->latest('date_emission')->limit(5)->get(),
        ];
    }
}
