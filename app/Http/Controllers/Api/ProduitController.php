<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProduitRequest;
use App\Http\Requests\UpdateProduitRequest;
use App\Models\Produit;
use App\Services\LimiteService;
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

    public function store(StoreProduitRequest $request, LimiteService $limiteService): JsonResponse
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

        $produit = Produit::create($data);

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

    public function destroy(Produit $produit): JsonResponse
    {
        $this->authorize('delete', $produit);

        $produit->delete();

        return response()->json(['message' => 'Produit/service supprimé.']);
    }
}
