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
        $stats = $statsService->pourCompte(
            $request->user(),
            $request->string('debut')->toString() ?: null,
            $request->string('fin')->toString() ?: null,
        );

        return Inertia::render('Compte/Dashboard', [
            'stats' => $stats,
            'filtres' => $request->only(['debut', 'fin']),
        ]);
    }
}
