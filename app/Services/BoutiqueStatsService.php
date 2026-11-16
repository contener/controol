<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Produit;
use Carbon\Carbon;

class BoutiqueStatsService
{
    public function __construct(
        private readonly ClientSegmentationService $segmentation,
    ) {
    }

    public function pourBoutique(Boutique $boutique, ?string $debut = null, ?string $fin = null): array
    {
        [$debut, $fin] = $this->resoudrePeriode($debut, $fin);
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

        $beneficeMois = $this->benefice($boutique, now()->startOfMonth(), now()->endOfMonth());

        $caPeriode = (float) $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])
            ->whereBetween('date_emission', [$debut, $fin])
            ->sum('total_ttc');

        $depensesPeriode = (float) $boutique->depenses()
            ->whereBetween('date_depense', [$debut, $fin])
            ->sum('montant');

        $beneficePeriode = $this->benefice($boutique, $debut, $fin);

        $nombreFacturesPeriode = $facturesQuery()->whereIn('statut', ['envoyee', 'payee'])
            ->whereBetween('date_emission', [$debut, $fin])
            ->count();

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
            // Bénéfice brut = ventes − prix d'achat des produits vendus (benefice()) ;
            // bénéfice net = bénéfice brut − charges (dépenses de la période) ; marges
            // brute et nette = ces bénéfices rapportés au chiffre d'affaires (2026-11-16).
            // benefice_mois / benefice / marge_pourcentage restent envoyés (= brut) pour
            // les anciennes versions de l'application ; resultat (CA − dépenses) aussi.
            'finances' => [
                'ca_mois' => (float) $caMois,
                'depenses_mois' => (float) $depensesMois,
                'resultat_mois' => (float) $caMois - (float) $depensesMois,
                'benefice_mois' => $beneficeMois,
                'benefice_brut_mois' => $beneficeMois,
                'benefice_net_mois' => $beneficeMois - (float) $depensesMois,
                'marge_brute_mois' => $this->marge($beneficeMois, (float) $caMois),
                'marge_nette_mois' => $this->marge($beneficeMois - (float) $depensesMois, (float) $caMois),
            ],
            // Bloc "période personnalisée" (voir resoudrePeriode(), même principe que
            // CompteStatsService::pourCompte()) -- par défaut le mois en cours, mais
            // sélectionnable depuis Dashboard.vue pour analyser n'importe quelle période.
            'periode' => [
                'ca' => $caPeriode,
                'depenses' => $depensesPeriode,
                'benefice' => $beneficePeriode,
                'benefice_brut' => $beneficePeriode,
                'benefice_net' => $beneficePeriode - $depensesPeriode,
                'resultat' => $caPeriode - $depensesPeriode,
                'marge_pourcentage' => $this->marge($beneficePeriode, $caPeriode),
                'marge_brute_pourcentage' => $this->marge($beneficePeriode, $caPeriode),
                'marge_nette_pourcentage' => $this->marge($beneficePeriode - $depensesPeriode, $caPeriode),
                'nombre_factures' => $nombreFacturesPeriode,
                'panier_moyen' => $nombreFacturesPeriode > 0 ? $caPeriode / $nombreFacturesPeriode : 0.0,
                'debut' => $debut->toDateString(),
                'fin' => $fin->toDateString(),
            ],
            'produits_plus_vendus' => $produitsVendus,
            'factures_recentes' => $facturesQuery()->with('client:id,nom')->latest('date_emission')->limit(5)->get(),
        ];
    }

    /**
     * Bénéfice brut (marge) = somme sur les lignes vendues de (montant_ht − coût
     * d'achat). Le coût utilisé est le prix_achat ACTUEL du produit (facture_lignes
     * n'a pas de colonne prix_achat propre, donc pas d'historique si ce prix change
     * après la vente -- simplification assumée, cohérente avec un outil de gestion
     * courante plutôt qu'une comptabilité au centime près). Une ligne libre (pas de
     * produit_id, ex. une prestation sans équivalent catalogue) n'a aucun coût connu :
     * tout son montant_ht compte comme bénéfice plutôt que de lui inventer un coût.
     * Même filtre statut que ca_mois/chiffre_affaires (envoyee/payee, tous types
     * confondus y compris Proforma) -- volontairement, pour que "bénéfice" reste une
     * simple décomposition du même CA affiché ailleurs sur ce tableau de bord, jamais
     * un nombre basé sur une autre population de factures.
     */
    private function benefice(Boutique $boutique, Carbon $debut, Carbon $fin): float
    {
        return (float) (FactureLigne::query()
            ->join('factures', 'factures.id', '=', 'facture_lignes.facture_id')
            ->leftJoin('produits', 'produits.id', '=', 'facture_lignes.produit_id')
            ->where('factures.boutique_id', $boutique->id)
            ->whereIn('factures.statut', ['envoyee', 'payee'])
            ->whereBetween('factures.date_emission', [$debut, $fin])
            ->selectRaw('COALESCE(SUM(facture_lignes.montant_ht - COALESCE(produits.prix_achat, 0) * facture_lignes.quantite), 0) as total')
            ->value('total') ?? 0);
    }

    /** Marge en % du chiffre d'affaires, arrondie à 0,1 -- 0 sans chiffre d'affaires. */
    private function marge(float $benefice, float $chiffreAffaires): float
    {
        return $chiffreAffaires > 0 ? round(($benefice / $chiffreAffaires) * 100, 1) : 0.0;
    }

    private function resoudrePeriode(?string $debut, ?string $fin): array
    {
        if ($debut && $fin) {
            return [Carbon::parse($debut)->startOfDay(), Carbon::parse($fin)->endOfDay()];
        }

        return [now()->startOfMonth(), now()->endOfMonth()];
    }
}
