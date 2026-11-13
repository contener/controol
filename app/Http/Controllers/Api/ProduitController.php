<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProduitRequest;
use App\Http\Requests\UpdateProduitRequest;
use App\Models\Produit;
use App\Services\LimiteService;
use App\Services\NotificationAbonnesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de ProduitController (web) -- même logique de bascule
 * gere_stock/limites que store()/update() côté web, intentionnellement dupliquée
 * ici plutôt que d'extraire un Service partagé tout de suite (voir controol.md
 * §5.21) : si cette logique évolue, mettre à jour LES DEUX côtés.
 */
class ProduitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Produit::class);

        $produits = Produit::query()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('reference', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->orderBy('nom')
            ->paginate($request->integer('par_page', 15))
            ->withQueryString();

        return response()->json($produits);
    }

    public function store(StoreProduitRequest $request, LimiteService $limiteService, NotificationAbonnesService $notifications): JsonResponse
    {
        if (! $limiteService->peutCreerProduit($request->user())) {
            return response()->json([
                'message' => "Limite de produits/services atteinte pour votre plan ({$request->user()->planActif()?->nom}).",
            ], 422);
        }

        $data = $request->validated();
        $data['gere_stock'] = $data['type'] === 'produit' && ($data['gere_stock'] ?? false);
        $data['actif'] = $data['actif'] ?? true;

        if (! $data['gere_stock']) {
            $data['quantite_stock'] = 0;
            $data['seuil_alerte'] = null;
        } elseif (! $limiteService->peutActiverStockSupplementaire($request->user())) {
            return response()->json([
                'message' => "Limite de produits suivis en stock atteinte pour votre plan ({$request->user()->planActif()?->nom}).",
            ], 422);
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('produits', 'public');
        }

        // Même règle que ProduitController::store() (web) : visible d'office,
        // désactivable ensuite (updateMarketplace()), abonnés notifiés à la création.
        $data['marketplace_visible'] = true;

        $produit = Produit::create($data);

        if ($produit->actif) {
            $notifications->nouveauProduit($produit);
        }

        return response()->json($produit, 201);
    }

    public function show(Produit $produit): JsonResponse
    {
        $this->authorize('view', $produit);

        return response()->json($produit);
    }

    public function update(UpdateProduitRequest $request, Produit $produit, LimiteService $limiteService): JsonResponse
    {
        $data = $request->validated();
        $data['gere_stock'] = $data['type'] === 'produit' && ($data['gere_stock'] ?? false);

        if (! $data['gere_stock']) {
            $data['quantite_stock'] = $produit->quantite_stock;
            $data['seuil_alerte'] = null;
        } elseif (! $produit->gere_stock && ! $limiteService->peutActiverStockSupplementaire($request->user())) {
            return response()->json([
                'message' => "Limite de produits suivis en stock atteinte pour votre plan ({$request->user()->planActif()?->nom}).",
            ], 422);
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('produits', 'public');
        }

        $produit->update($data);

        return response()->json($produit->fresh());
    }

    /**
     * Même règle que ProduitController::updateMarketplace() (web) : les abonnés ne
     * sont notifiés qu'au vrai passage invisible -> visible d'un produit actif
     * (NotificationAbonnesService, partagé avec le web).
     */
    public function updateMarketplace(Request $request, Produit $produit, NotificationAbonnesService $notifications): JsonResponse
    {
        $this->authorize('update', $produit);

        $data = $request->validate(['marketplace_visible' => ['required', 'boolean']]);

        // La boutique publique n'affiche que les produits actifs ET visibles : côté
        // mobile, "Activer" signifie "le montrer dans ma boutique", donc réactive
        // aussi un produit inactif -- sinon il resterait invisible malgré
        // marketplace_visible = true (cas réel constaté le 2026-11-13). "Désactiver"
        // ne touche qu'à la visibilité. Abonnés notifiés seulement au vrai passage
        // "non affiché" -> "affiché".
        $etaitAffiche = $produit->marketplace_visible && $produit->actif;

        $produit->update($data['marketplace_visible']
            ? ['marketplace_visible' => true, 'actif' => true]
            : ['marketplace_visible' => false]);

        if ($data['marketplace_visible'] && ! $etaitAffiche) {
            $notifications->nouveauProduit($produit);
        }

        return response()->json([
            'message' => $data['marketplace_visible']
                ? "« {$produit->nom} » est désormais visible dans la boutique et la Marketplace."
                : "« {$produit->nom} » n'est plus visible publiquement.",
            'produit' => $produit->fresh(),
        ]);
    }

    public function destroy(Produit $produit): JsonResponse
    {
        $this->authorize('delete', $produit);

        $produit->delete();

        return response()->json(['message' => 'Produit/service supprimé.']);
    }
}
