<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FournitEssaiActif;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Plan;
use App\Services\AbonnementService;
use App\Services\LimiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API d'AbonnementController -- la demande de changement passe par
 * AbonnementService (partagé avec le web). Le lien de paiement hébergé est renvoyé
 * à l'app, qui l'ouvre dans le navigateur du téléphone (le web, lui, y redirige).
 */
class AbonnementController extends Controller
{
    use FournitEssaiActif;

    public function index(Request $request, LimiteService $limiteService): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'plan_actif' => $user->planActif(),
            'usage' => $limiteService->usage($user),
            'plans' => Plan::orderBy('ordre')->get(),
            'paiement_en_attente' => Paiement::where('user_id', $user->id)->where('statut', 'en_attente')->latest()->first(),
            'essai_actif' => $this->essaiActif($user),
        ]);
    }

    public function changer(Request $request, Plan $plan, AbonnementService $abonnements): JsonResponse
    {
        return response()->json($abonnements->demanderChangement($request->user(), $plan));
    }
}
