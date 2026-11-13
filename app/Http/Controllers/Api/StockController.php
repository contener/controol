<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMouvementStockRequest;
use App\Models\Produit;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de StockController (web). Voir controol.md §5.21.
 */
class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Produit::class);

        $produits = Produit::query()
            ->where('type', 'produit')
            ->where('gere_stock', true)
            ->when($request->boolean('rupture'), function ($query) {
                $query->whereColumn('quantite_stock', '<=', 'seuil_alerte')->whereNotNull('seuil_alerte');
            })
            ->orderBy('nom')
            ->paginate($request->integer('par_page', 15))
            ->withQueryString();

        return response()->json($produits);
    }

    public function mouvements(Produit $produit): JsonResponse
    {
        $this->authorize('view', $produit);

        $mouvements = $produit->mouvementsStock()
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($mouvements);
    }

    public function store(StoreMouvementStockRequest $request, StockService $stockService): JsonResponse
    {
        $produit = Produit::findOrFail($request->validated('produit_id'));

        $stockService->enregistrerMouvement(
            $produit,
            $request->validated('type'),
            (int) $request->validated('quantite'),
            $request->validated('motif'),
            $request->user()->id,
        );

        return response()->json(['message' => 'Mouvement de stock enregistré.', 'produit' => $produit->fresh()]);
    }
}
