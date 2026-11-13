<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BoutiqueStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de DashboardController (web) -- même service, mêmes permissions
 * (voir Boutique::autoriseDashboard()), réponse JSON au lieu d'une page Inertia.
 */
class DashboardController extends Controller
{
    public function index(Request $request, BoutiqueStatsService $statsService): JsonResponse
    {
        $user = $request->user();
        $boutique = $user->currentBoutique;

        if (! $boutique) {
            return response()->json(['aucune_boutique' => true]);
        }

        if (! $boutique->autoriseDashboard($user)) {
            return response()->json([
                'acces_refuse' => true,
                'message' => "Vous n'avez pas accès au tableau de bord de cette boutique.",
            ], 403);
        }

        return response()->json([
            'boutique' => $boutique->only(['id', 'nom', 'slug', 'devise', 'statut']),
            'stats' => $statsService->pourBoutique(
                $boutique,
                $request->string('debut')->toString() ?: null,
                $request->string('fin')->toString() ?: null,
            ),
        ]);
    }
}
