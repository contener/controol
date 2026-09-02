<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMouvementStockRequest;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Produit::class);

        $produits = Produit::query()
            ->where('type', 'produit')
            ->where('gere_stock', true)
            ->when($request->boolean('rupture'), function ($query) {
                $query->whereColumn('quantite_stock', '<=', 'seuil_alerte')->whereNotNull('seuil_alerte');
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Stock/Index', [
            'produits' => $produits,
            'filtres' => $request->only(['rupture']),
        ]);
    }

    public function mouvements(Produit $produit): Response
    {
        $this->authorize('view', $produit);

        $mouvements = $produit->mouvementsStock()
            ->with('user:id,name')
            ->latest()
            ->paginate(20);

        return Inertia::render('Stock/Mouvements', [
            'produit' => $produit,
            'mouvements' => $mouvements,
        ]);
    }

    public function store(StoreMouvementStockRequest $request, StockService $stockService): RedirectResponse
    {
        $produit = Produit::findOrFail($request->validated('produit_id'));

        $stockService->enregistrerMouvement(
            $produit,
            $request->validated('type'),
            (int) $request->validated('quantite'),
            $request->validated('motif'),
            $request->user()->id,
        );

        return back()->with('flash_success', 'Mouvement de stock enregistré avec succès.');
    }
}
