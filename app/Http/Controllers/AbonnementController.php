<?php

namespace App\Http\Controllers;

use App\Enums\EssaiStatut;
use App\Models\EssaiUtilisateur;
use App\Models\Paiement;
use App\Models\Plan;
use App\Services\AbonnementService;
use App\Services\LimiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AbonnementController extends Controller
{
    public function index(Request $request, LimiteService $limiteService): Response
    {
        $user = $request->user();
        $essai = EssaiUtilisateur::where('user_id', $user->id)->latest('id')->first();
        $essaiEnCours = $essai && $essai->statut() === EssaiStatut::EnCours;

        return Inertia::render('Abonnement/Index', [
            'planActif' => $user->planActif(),
            'usage' => $limiteService->usage($user),
            'plans' => Plan::orderBy('ordre')->get(),
            'paiementEnAttente' => Paiement::where('user_id', $user->id)->where('statut', 'en_attente')->latest()->first(),
            'essaiActif' => $essaiEnCours ? [
                'jours_restants' => $essai->joursRestants(),
                'date_fin' => $essai->date_fin->format('d/m/Y'),
                'prix_promo' => (float) $essai->prix_promo,
                'prix_normal' => (float) $essai->plan->prix,
            ] : null,
        ]);
    }

    /**
     * Logique métier dans AbonnementService (partagée avec Api\AbonnementController) --
     * ce contrôleur ne fait que traduire le résultat en réponse web.
     */
    public function demanderChangement(Request $request, Plan $plan, AbonnementService $abonnements): RedirectResponse|SymfonyResponse
    {
        $resultat = $abonnements->demanderChangement($request->user(), $plan);

        if ($resultat['lien_paiement']) {
            return Inertia::location($resultat['lien_paiement']);
        }

        return back()->with('flash_success', $resultat['message']);
    }
}
