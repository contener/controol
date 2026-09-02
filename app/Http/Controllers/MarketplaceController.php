<?php

namespace App\Http\Controllers;

use App\Models\Boutique;
use App\Models\Produit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    private const CHAMPS_PUBLICS = ['id', 'nom', 'slug', 'logo_path', 'categorie', 'ville', 'pays'];

    public function index(Request $request): Response
    {
        $boutiques = Boutique::eligiblesMarketplace()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhereHas('produits', function ($pq) use ($recherche) {
                            $pq->withoutGlobalScopes()->where('actif', true)->where('nom', 'like', "%{$recherche}%");
                        });
                });
            })
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->string('ville')->toString(), fn ($q, $v) => $q->where('ville', $v))
            ->withCount(['produits' => fn ($q) => $q->withoutGlobalScopes()->where('actif', true)])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn ($boutique) => [
                ...$boutique->only(self::CHAMPS_PUBLICS),
                'produits_count' => $boutique->produits_count,
            ]);

        $categories = Boutique::eligiblesMarketplace()->whereNotNull('categorie')->distinct()->pluck('categorie');
        $villes = Boutique::eligiblesMarketplace()->whereNotNull('ville')->distinct()->pluck('ville');

        $boutiqueIdsEligibles = Boutique::eligiblesMarketplace()->pluck('id');
        $promotions = Produit::withoutGlobalScopes()
            ->whereIn('boutique_id', $boutiqueIdsEligibles)
            ->where('actif', true)
            ->whereNotNull('promotion_prix')
            ->with('boutique:id,nom,slug,devise')
            ->latest()
            ->limit(8)
            ->get(['id', 'boutique_id', 'nom', 'photo_path', 'prix_vente', 'promotion_prix']);

        return Inertia::render('Marketplace/Index', [
            'boutiques' => $boutiques,
            'categories' => $categories,
            'villes' => $villes,
            'promotions' => $promotions,
            'planAutoriseMarketplace' => (bool) $request->user()?->planActif()?->marketplace,
            'filtres' => $request->only(['recherche', 'categorie', 'ville']),
            'meta' => [
                'title' => 'Marketplace — Découvrez des boutiques locales',
                'description' => 'Parcourez les boutiques et produits disponibles sur la marketplace.',
                'url' => route('marketplace.index'),
                'type' => 'website',
            ],
        ]);
    }
}
