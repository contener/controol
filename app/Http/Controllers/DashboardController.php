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
        $user = $request->user();
        $boutique = $user->currentBoutique;

        if (! $boutique) {
            return Inertia::render('Dashboard', [
                'aucuneBoutique' => true,
                'essaiActif' => $this->essaiActif($request),
            ]);
        }

        // Le Dashboard est la page d'accueil par défaut après connexion (config/fortify.php
        // 'home') -- un collaborateur sans la permission "Dashboard" doit donc voir un
        // état d'accès refusé "doux" ici plutôt qu'un 403 brut, sans quoi il resterait
        // bloqué dès la connexion sans aucune indication de quoi faire.
        if (! $boutique->autoriseDashboard($user)) {
            return Inertia::render('Dashboard', [
                'accesRefuse' => true,
                'boutique' => $boutique->only(['id', 'nom', 'slug', 'devise', 'statut']),
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
