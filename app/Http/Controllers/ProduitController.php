<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProduitRequest;
use App\Http\Requests\UpdateProduitRequest;
use App\Models\Produit;
use App\Services\LimiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $produit = Produit::create($data);

        if ($data['actif']) {
            $this->notifierAbonnes($produit);
        }

        return redirect()->route('produits.index')->with('flash_success', 'Produit/service créé avec succès.');
    }

    /**
     * Insertion en masse (une seule requête) : jamais N notifications Eloquent
     * individuelles pour une boutique à forte audience. Ne part jamais vers un
     * abonné désactivé (notifications_actives = false) ou désabonné.
     */
    private function notifierAbonnes(Produit $produit): void
    {
        $destinataires = DB::table('suivis_boutique')
            ->where('boutique_id', $produit->boutique_id)
            ->where('notifications_actives', true)
            ->whereNull('desabonne_a')
            ->pluck('user_id');

        if ($destinataires->isEmpty()) {
            return;
        }

        $boutique = $produit->boutique;
        $prix = $produit->promotion_prix ?? $produit->prix_vente;

        $message = "{$boutique->nom} vient de publier « {$produit->nom} »";
        if ($produit->mini_characteristics) {
            $message .= " — {$produit->mini_characteristics}";
        }
        $message .= ' à '.number_format((float) $prix, 0, ',', ' ')." {$boutique->devise}.";

        // URL absolue construite ici, une seule fois, plutôt que devinée côté client à
        // partir d'un slug -- reste valide même si la structure des routes change.
        $lien = route('public.boutique', $boutique->slug);
        $maintenant = now();

        DB::table('notifications_utilisateurs')->insert(
            $destinataires->map(fn ($userId) => [
                'user_id' => $userId,
                'type' => 'nouveau_produit',
                'titre' => "Nouveau produit chez {$boutique->nom}",
                'message' => $message,
                'lien' => $lien,
                'est_promotionnelle' => false,
                'created_at' => $maintenant,
            ])->all()
        );
    }

    public function edit(Produit $produit): Response
    {
        $this->authorize('update', $produit);

        return Inertia::render('Produits/Edit', [
            'produit' => $produit,
        ]);
    }

    /**
     * Bascule rapide depuis la liste Produits & Services -- distincte de update() pour
     * ne jamais faire dépendre la visibilité Marketplace d'une soumission du formulaire
     * complet (mêmes principes que Boutique::updateMarketplace()).
     */
    public function updateMarketplace(Request $request, Produit $produit): RedirectResponse
    {
        $this->authorize('update', $produit);

        $data = $request->validate(['marketplace_visible' => ['required', 'boolean']]);

        $produit->update(['marketplace_visible' => $data['marketplace_visible']]);

        return back()->with('flash_success', $data['marketplace_visible']
            ? "« {$produit->nom} » est désormais visible dans la Marketplace."
            : "« {$produit->nom} » a été retiré de la Marketplace.");
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
