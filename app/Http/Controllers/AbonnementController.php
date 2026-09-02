<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Models\Plan;
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

        return Inertia::render('Abonnement/Index', [
            'planActif' => $user->planActif(),
            'usage' => $limiteService->usage($user),
            'plans' => Plan::orderBy('ordre')->get(),
            'paiementEnAttente' => Paiement::where('user_id', $user->id)->where('statut', 'en_attente')->latest()->first(),
        ]);
    }

    public function demanderChangement(Request $request, Plan $plan): RedirectResponse|SymfonyResponse
    {
        $user = $request->user();

        if ($plan->prix <= 0) {
            $user->abonnements()->create([
                'plan_id' => $plan->id,
                'statut' => 'actif',
                'date_debut' => now(),
            ]);

            return back()->with('flash_success', 'Vous êtes maintenant sur le plan Gratuit.');
        }

        $abonnement = $user->abonnements()->create([
            'plan_id' => $plan->id,
            'statut' => 'en_attente',
            'date_debut' => now(),
        ]);

        Paiement::create([
            'user_id' => $user->id,
            'abonnement_id' => $abonnement->id,
            'montant' => $plan->prix,
            'devise' => $plan->devise,
            'moyen_paiement' => 'manuel',
            'statut' => 'en_attente',
        ]);

        // L'abonnement reste "en_attente" tant qu'un Super Admin n'a pas vérifié et
        // approuvé le paiement (PaiementValidationService::approuver) — ce lien externe
        // ne fait qu'orienter l'utilisateur vers la page de paiement hébergée, il n'active
        // jamais l'abonnement lui-même (RULE 5).
        if ($plan->lien_paiement) {
            return Inertia::location($plan->lien_paiement);
        }

        return back()->with('flash_success', "Demande envoyée pour le plan {$plan->nom}. Votre abonnement sera activé dès confirmation du paiement ({$plan->prix} {$plan->devise}).");
    }
}
