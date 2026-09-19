<?php

namespace App\Http\Controllers;

use App\Enums\EssaiStatut;
use App\Models\EssaiUtilisateur;
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
            return Inertia::render('Dashboard', [
                'aucuneBoutique' => true,
                'essaiActif' => $this->essaiActif($request),
            ]);
        }

        return Inertia::render('Dashboard', [
            'boutique' => $boutique->only(['id', 'nom', 'slug', 'devise', 'statut']),
            'stats' => $statsService->pourBoutique($boutique),
            'essaiActif' => $this->essaiActif($request),
        ]);
    }

    private function essaiActif(Request $request): ?array
    {
        $essai = EssaiUtilisateur::where('user_id', $request->user()->id)->latest('id')->first();

        if (! $essai || $essai->statut() !== EssaiStatut::EnCours) {
            return null;
        }

        return [
            'jours_restants' => $essai->joursRestants(),
            'date_fin' => $essai->date_fin->format('d/m/Y'),
            'prix_promo' => (float) $essai->prix_promo,
            'prix_normal' => (float) $essai->plan->prix,
        ];
    }
}
