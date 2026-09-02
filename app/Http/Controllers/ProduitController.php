<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProduitRequest;
use App\Http\Requests\UpdateProduitRequest;
use App\Models\Produit;
use App\Services\LimiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProduitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Produit::class);

        $produits = Produit::query()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('reference', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('type')->toString(), function ($query, $type) {
                $query->where('type', $type);
            })
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Produits/Index', [
            'produits' => $produits,
            'filtres' => $request->only(['recherche', 'type']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Produit::class);

        return Inertia::render('Produits/Create');
    }

    public function store(StoreProduitRequest $request, LimiteService $limiteService): RedirectResponse
    {
        if (! $limiteService->peutCreerProduit($request->user())) {
            return back()->with('flash_error', "Limite de produits/services atteinte pour votre plan ({$request->user()->planActif()?->nom}). Passez à un plan supérieur pour en ajouter davantage.");
        }

        $data = $request->validated();
        $data['gere_stock'] = $data['type'] === 'produit' && ($data['gere_stock'] ?? false);
        $data['actif'] = $data['actif'] ?? true;

        if (! $data['gere_stock']) {
            $data['quantite_stock'] = 0;
            $data['seuil_alerte'] = null;
        } elseif (! $limiteService->peutActiverStockSupplementaire($request->user())) {
            return back()->with('flash_error', "Limite de produits suivis en stock atteinte pour votre plan ({$request->user()->planActif()?->nom}).");
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('produits', 'public');
        }

        Produit::create($data);

        return redirect()->route('produits.index')->with('flash_success', 'Produit/service créé avec succès.');
    }

    public function edit(Produit $produit): Response
    {
        $this->authorize('update', $produit);

        return Inertia::render('Produits/Edit', [
            'produit' => $produit,
        ]);
    }

    public function update(UpdateProduitRequest $request, Produit $produit, LimiteService $limiteService): RedirectResponse
    {
        $data = $request->validated();
        $data['gere_stock'] = $data['type'] === 'produit' && ($data['gere_stock'] ?? false);

        if (! $data['gere_stock']) {
            $data['quantite_stock'] = $produit->quantite_stock;
            $data['seuil_alerte'] = null;
        } elseif (! $produit->gere_stock && ! $limiteService->peutActiverStockSupplementaire($request->user())) {
            return back()->with('flash_error', "Limite de produits suivis en stock atteinte pour votre plan ({$request->user()->planActif()?->nom}).");
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('produits', 'public');
        }

        $produit->update($data);

        return redirect()->route('produits.index')->with('flash_success', 'Produit/service mis à jour avec succès.');
    }

    public function destroy(Produit $produit): RedirectResponse
    {
        $this->authorize('delete', $produit);

        $produit->delete();

        return redirect()->route('produits.index')->with('flash_success', 'Produit/service supprimé avec succès.');
    }
}
