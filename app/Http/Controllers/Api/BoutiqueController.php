<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoutiqueRequest;
use App\Http\Requests\UpdateBoutiqueRequest;
use App\Models\Boutique;
use App\Services\InvitationBoutiqueService;
use App\Services\LimiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Équivalent API de BoutiqueController (web) : lecture/changement de boutique
 * courante (index/switch), et gestion complète des boutiques possédées (page "Mes
 * boutiques") -- mêmes FormRequests (compression logo/bannière incluse), mêmes
 * Policies, même vérification de limite de plan, même verrou Marketplace.
 */
class BoutiqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Même fusion propriétaire + collaborateur que HandleInertiaRequests::share()
        // (mesBoutiques, web) -- jamais deux définitions divergentes de "mes boutiques".
        $possedees = $user->boutiques()->get(['id', 'nom', 'slug', 'devise', 'statut', 'logo_path'])
            ->map(fn ($b) => [...$b->toArray(), 'est_proprietaire' => true]);

        $collaborees = $user->boutiquesCollaborateur()
            ->get(['boutiques.id', 'boutiques.nom', 'boutiques.slug', 'boutiques.devise', 'boutiques.statut', 'boutiques.logo_path'])
            ->map(fn ($b) => [
                ...$b->toArray(),
                'est_proprietaire' => false,
                'permissions' => collect(Boutique::PERMISSIONS_COLLABORATEUR)
                    ->mapWithKeys(fn ($colonne) => [str_replace('acces_', '', $colonne) => (bool) $b->pivot->{$colonne}]),
            ]);

        return response()->json([
            'boutiques' => $possedees->concat($collaborees)->sortBy('nom')->values(),
            'boutique_courante_id' => $user->current_boutique_id,
        ]);
    }

    /** Page "Mes boutiques" (BoutiqueController::index() web). */
    public function gestion(Request $request, LimiteService $limiteService): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'boutiques' => $user->boutiques()->withCount(['clients', 'produits', 'factures'])->latest()->get()
                ->map(fn (Boutique $b) => [...$b->toArray(), 'url_publique' => route('public.boutique', $b->slug)]),
            'plan_autorise_marketplace' => (bool) $user->planActif()?->marketplace,
            'peut_creer' => $limiteService->peutCreerBoutique($user),
            'boutique_courante_id' => $user->current_boutique_id,
        ]);
    }

    public function show(Boutique $boutique): JsonResponse
    {
        $this->authorize('update', $boutique);

        return response()->json([
            'boutique' => $boutique,
            'plan_autorise_marketplace' => (bool) $boutique->proprietaire->planActif()?->marketplace,
        ]);
    }

    public function store(StoreBoutiqueRequest $request, LimiteService $limiteService): JsonResponse
    {
        $user = $request->user();

        if (! $limiteService->peutCreerBoutique($user)) {
            return response()->json([
                'message' => "Limite de boutiques atteinte pour votre plan ({$user->planActif()?->nom}). Passez à un plan supérieur pour créer plus de boutiques.",
            ], 422);
        }

        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['slug'] = Boutique::genererSlugUnique($data['nom']);
        // Publiée d'office sur la Marketplace quand le plan le permet (2026-11-13) --
        // désactivable ensuite (updateMarketplace()), jamais activée sans plan.
        $data['marketplace_visible'] = (bool) $user->planActif()?->marketplace;

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('boutiques/logos', 'public');
        }

        if ($request->hasFile('banniere')) {
            $data['banniere_path'] = $request->file('banniere')->store('boutiques/bannieres', 'public');
        }

        $boutique = Boutique::create($data);

        app(InvitationBoutiqueService::class)->apresCreationBoutique($user, $boutique);

        if (! $user->current_boutique_id) {
            $user->switchBoutique($boutique);
        }

        return response()->json(['message' => 'Boutique créée avec succès.', 'boutique' => $boutique], 201);
    }

    /**
     * Appelé en POST (multipart, avec _method=PUT ou non) pour pouvoir envoyer logo et
     * bannière -- PHP ne lit pas les fichiers d'une vraie requête PUT multipart.
     */
    public function update(UpdateBoutiqueRequest $request, Boutique $boutique): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('boutiques/logos', 'public');
        }

        if ($request->hasFile('banniere')) {
            $data['banniere_path'] = $request->file('banniere')->store('boutiques/bannieres', 'public');
        }

        $boutique->update($data);

        return response()->json(['message' => 'Boutique mise à jour avec succès.', 'boutique' => $boutique->fresh()]);
    }

    public function destroy(Request $request, Boutique $boutique): JsonResponse
    {
        $this->authorize('delete', $boutique);

        $user = $request->user();
        $etaitCourante = $user->current_boutique_id === $boutique->id;

        $boutique->delete();

        if ($etaitCourante) {
            $nouvelleBoutique = $user->boutiques()->first();
            $user->switchBoutique($nouvelleBoutique);

            if (! $nouvelleBoutique) {
                $user->forceFill(['current_boutique_id' => null])->save();
            }
        }

        return response()->json(['message' => 'Boutique supprimée avec succès.']);
    }

    public function switch(Request $request, Boutique $boutique): JsonResponse
    {
        $this->authorize('basculer', $boutique);

        $request->user()->switchBoutique($boutique);

        return response()->json(['message' => 'Boutique changée.', 'boutique_courante_id' => $boutique->id]);
    }

    public function updateMarketplace(Request $request, Boutique $boutique): JsonResponse
    {
        $this->authorize('update', $boutique);

        $data = $request->validate(['marketplace_visible' => ['required', 'boolean']]);

        // Jamais activé sans un abonnement qui l'autorise, même requête forgée.
        $planAutorise = (bool) $boutique->proprietaire->planActif()?->marketplace;

        $boutique->update(['marketplace_visible' => $data['marketplace_visible'] && $planAutorise]);

        if ($data['marketplace_visible'] && ! $planAutorise) {
            return response()->json([
                'message' => "Votre abonnement actuel ne permet pas d'afficher votre boutique dans la Marketplace. Passez au plan Basique ou Pro.",
            ], 422);
        }

        return response()->json(['message' => 'Préférence Marketplace mise à jour.', 'marketplace_visible' => $boutique->marketplace_visible]);
    }
}
