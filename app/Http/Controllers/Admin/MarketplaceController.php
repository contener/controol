<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Protégé par le middleware 'super_admin' sur le groupe de routes /admin (routes/web.php)
 * — voir Admin\PaiementController pour le même schéma.
 */
class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $boutiques = Boutique::query()
            ->with(['proprietaire:id,name,email'])
            ->withCount('produits')
            ->when($request->string('recherche')->toString(), function ($q, $recherche) {
                $q->where('nom', 'like', "%{$recherche}%");
            })
            ->when($request->string('statut_marketplace')->toString(), function ($q, $statut) {
                match ($statut) {
                    'eligibles' => $q->eligiblesMarketplace(),
                    'desactivees' => $q->where('marketplace_disabled_by_admin', true),
                    'non_eligibles' => $q->where(function ($sq) {
                        $sq->where('marketplace_visible', false)
                            ->orWhereDoesntHave('proprietaire.abonnements', fn ($aq) => $aq->actuellementActif()->whereHas('plan', fn ($pq) => $pq->where('marketplace', true)));
                    }),
                    default => null,
                };
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $boutiques->getCollection()->transform(function (Boutique $boutique) {
            return [
                'id' => $boutique->id,
                'nom' => $boutique->nom,
                'slug' => $boutique->slug,
                'statut' => $boutique->statut,
                'marketplace_visible' => $boutique->marketplace_visible,
                'marketplace_disabled_by_admin' => $boutique->marketplace_disabled_by_admin,
                'eligible' => $boutique->estEligibleMarketplace(),
                'produits_count' => $boutique->produits_count,
                'proprietaire' => $boutique->proprietaire,
                'plan' => $boutique->proprietaire->planActif()?->nom,
            ];
        });

        return Inertia::render('Admin/Marketplace/Index', [
            'boutiques' => $boutiques,
            'filtres' => $request->only(['recherche', 'statut_marketplace']),
        ]);
    }

    public function basculer(Boutique $boutique): RedirectResponse
    {
        $boutique->update(['marketplace_disabled_by_admin' => ! $boutique->marketplace_disabled_by_admin]);

        $message = $boutique->marketplace_disabled_by_admin
            ? "Boutique {$boutique->nom} désactivée de la Marketplace."
            : "Boutique {$boutique->nom} réactivée dans la Marketplace.";

        return back()->with('flash_success', $message);
    }
}
