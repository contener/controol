<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de BoutiqueController::index()/switch() -- seulement la lecture et
 * le changement de boutique courante pour l'app mobile en v1 ; créer/modifier les
 * réglages d'une boutique (logo, bannière, NUI...) reste une tâche occasionnelle,
 * laissée au web pour l'instant (voir controol.md §5.21).
 */
class BoutiqueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Même fusion propriétaire + collaborateur que HandleInertiaRequests::share()
        // (mesBoutiques, web) -- jamais deux définitions divergentes de "mes boutiques".
        $possedees = $user->boutiques()->get(['id', 'nom', 'slug', 'devise', 'statut'])
            ->map(fn ($b) => [...$b->toArray(), 'est_proprietaire' => true]);

        $collaborees = $user->boutiquesCollaborateur()
            ->get(['boutiques.id', 'boutiques.nom', 'boutiques.slug', 'boutiques.devise', 'boutiques.statut'])
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

    public function switch(Request $request, Boutique $boutique): JsonResponse
    {
        $this->authorize('view', $boutique);

        $request->user()->switchBoutique($boutique);

        return response()->json(['message' => 'Boutique changée.', 'boutique_courante_id' => $boutique->id]);
    }
}
