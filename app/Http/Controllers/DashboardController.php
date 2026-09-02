<?php

namespace App\Http\Controllers;

use App\Services\BoutiqueStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BoutiqueStatsService $statsService): Response
    {
        $boutique = $request->user()->currentBoutique;

        if (! $boutique) {
            return Inertia::render('Dashboard', ['aucuneBoutique' => true]);
        }

        return Inertia::render('Dashboard', [
            'boutique' => $boutique->only(['id', 'nom', 'slug', 'devise', 'statut']),
            'stats' => $statsService->pourBoutique($boutique),
        ]);
    }
}
