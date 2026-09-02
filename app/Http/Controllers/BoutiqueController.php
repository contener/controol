<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoutiqueRequest;
use App\Http\Requests\UpdateBoutiqueRequest;
use App\Models\Boutique;
use App\Services\LimiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BoutiqueController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $boutiques = $user->boutiques()->withCount(['clients', 'produits', 'factures'])->latest()->get();

        return Inertia::render('Boutiques/Index', [
            'boutiques' => $boutiques,
            'planAutoriseMarketplace' => (bool) $user->planActif()?->marketplace,
        ]);
    }

    public function create(LimiteService $limiteService): Response
    {
        $this->authorize('create', Boutique::class);

        return Inertia::render('Boutiques/Create', [
            'peutCreer' => $limiteService->peutCreerBoutique(auth()->user()),
        ]);
    }

    public function store(StoreBoutiqueRequest $request, LimiteService $limiteService): RedirectResponse
    {
        $user = $request->user();

        if (! $limiteService->peutCreerBoutique($user)) {
            return back()->with('flash_error', "Limite de boutiques atteinte pour votre plan ({$user->planActif()?->nom}). Passez à un plan supérieur pour créer plus de boutiques.");
        }

        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['slug'] = $this->genererSlugUnique($data['nom']);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('boutiques/logos', 'public');
        }

        if ($request->hasFile('banniere')) {
            $data['banniere_path'] = $request->file('banniere')->store('boutiques/bannieres', 'public');
        }

        $boutique = Boutique::create($data);

        if (! $user->current_boutique_id) {
            $user->switchBoutique($boutique);
        }

        return redirect()->route('boutiques.index')->with('flash_success', 'Boutique créée avec succès.');
    }

    public function edit(Boutique $boutique): Response
    {
        $this->authorize('update', $boutique);

        $plan = $boutique->proprietaire->planActif();

        return Inertia::render('Boutiques/Edit', [
            'boutique' => $boutique,
            'planAutoriseMarketplace' => (bool) $plan?->marketplace,
        ]);
    }

    public function update(UpdateBoutiqueRequest $request, Boutique $boutique): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('boutiques/logos', 'public');
        }

        if ($request->hasFile('banniere')) {
            $data['banniere_path'] = $request->file('banniere')->store('boutiques/bannieres', 'public');
        }

        $boutique->update($data);

        return redirect()->route('boutiques.index')->with('flash_success', 'Boutique mise à jour avec succès.');
    }

    public function destroy(Boutique $boutique): RedirectResponse
    {
        $this->authorize('delete', $boutique);

        $user = auth()->user();
        $etaitCourante = $user->current_boutique_id === $boutique->id;

        $boutique->delete();

        if ($etaitCourante) {
            $nouvelleBoutique = $user->boutiques()->first();
            $user->switchBoutique($nouvelleBoutique);

            if (! $nouvelleBoutique) {
                $user->forceFill(['current_boutique_id' => null])->save();
            }
        }

        return redirect()->route('boutiques.index')->with('flash_success', 'Boutique supprimée avec succès.');
    }

    public function switch(Boutique $boutique): RedirectResponse
    {
        $this->authorize('view', $boutique);

        auth()->user()->switchBoutique($boutique);

        return redirect()->route('dashboard');
    }

    public function updateMarketplace(Request $request, Boutique $boutique): RedirectResponse
    {
        $this->authorize('update', $boutique);

        $data = $request->validate(['marketplace_visible' => ['required', 'boolean']]);

        // On ne peut jamais activer marketplace_visible sans un abonnement qui l'autorise
        // — même si le client forge la requête, le backend retranche silencieusement.
        $planAutorise = (bool) $boutique->proprietaire->planActif()?->marketplace;

        $boutique->update([
            'marketplace_visible' => $data['marketplace_visible'] && $planAutorise,
        ]);

        if ($data['marketplace_visible'] && ! $planAutorise) {
            return back()->with('flash_error', "Votre abonnement actuel ne permet pas d'afficher votre boutique dans la Marketplace. Passez au plan Basique ou Pro.");
        }

        return back()->with('flash_success', 'Préférence Marketplace mise à jour.');
    }

    private function genererSlugUnique(string $nom): string
    {
        $base = Str::slug($nom);
        $slug = $base;
        $i = 1;

        while (Boutique::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
