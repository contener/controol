<?php

namespace App\Support;

use App\Models\Boutique;
use App\Models\MouvementCaisse;
use App\Services\CaisseService;
use Illuminate\Http\Request;

/**
 * Données de la page Caisse, partagées par le site (CaisseController) et l'API
 * (Api\CaisseController) : solde, totaux de la période filtrée et mouvements.
 * Filtres : ?sens=entree|sortie, ?origine=apport|retrait|vente|depense, ?debut=, ?fin=.
 */
class JournalCaisse
{
    public static function pour(Boutique $boutique, Request $request, int $parPage = 20): array
    {
        $filtres = [
            'sens' => in_array($request->string('sens')->toString(), [MouvementCaisse::ENTREE, MouvementCaisse::SORTIE], true) ? $request->string('sens')->toString() : '',
            'origine' => in_array($request->string('origine')->toString(), MouvementCaisse::ORIGINES, true) ? $request->string('origine')->toString() : '',
            'debut' => $request->date('debut')?->toDateString() ?? '',
            'fin' => $request->date('fin')?->toDateString() ?? '',
        ];

        $requete = fn () => MouvementCaisse::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->when($filtres['debut'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtres['fin'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $totaux = $requete()
            ->selectRaw("COALESCE(SUM(CASE WHEN sens = 'entree' THEN montant ELSE 0 END), 0) as entrees")
            ->selectRaw("COALESCE(SUM(CASE WHEN sens = 'sortie' THEN montant ELSE 0 END), 0) as sorties")
            ->first();

        $mouvements = $requete()
            ->when($filtres['sens'], fn ($q, $s) => $q->where('sens', $s))
            ->when($filtres['origine'], fn ($q, $o) => $q->where('origine', $o))
            ->with([
                'facture' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'numero'),
                'depense' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'categorie'),
                'auteur:id,name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate($parPage)
            ->withQueryString();

        return [
            'solde' => app(CaisseService::class)->solde($boutique->id),
            'devise' => $boutique->devise,
            'periode' => [
                'entrees' => round((float) $totaux->entrees, 2),
                'sorties' => round((float) $totaux->sorties, 2),
            ],
            'mouvements' => $mouvements,
            'filtres' => $filtres,
            'caisse_ouverte_le' => $boutique->caisseOuverteLe()->toIso8601String(),
        ];
    }
}
