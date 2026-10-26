<?php

namespace App\Http\Controllers;

use App\Services\CompteStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompteDashboardController extends Controller
{
    public function __invoke(Request $request, CompteStatsService $statsService): Response
    {
        $user = $request->user();

        // Permission dédiée (voir Boutique::PERMISSIONS_COLLABORATEUR) -- seulement
        // vérifiée quand une boutique courante existe : un utilisateur sans boutique
        // (ou le propriétaire de la sienne) garde toujours accès, même vide.
        abort_if(
            $user->currentBoutique && ! $user->currentBoutique->autoriseVueGlobale($user),
            403,
            "Vous n'avez pas accès à la vue globale. Demandez l'accès au propriétaire de la boutique."
        );

        $stats = $statsService->pourCompte(
            $user,
            $request->string('debut')->toString() ?: null,
            $request->string('fin')->toString() ?: null,
        );

        return Inertia::render('Compte/Dashboard', [
            'stats' => $stats,
            'filtres' => $request->only(['debut', 'fin']),
        ]);
    }
}
