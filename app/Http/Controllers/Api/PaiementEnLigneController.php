<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\TentativePaiement;
use App\Services\PaiementEnLigneService;
use App\Support\HistoriquePaiements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Paiement d'un abonnement depuis l'application (CamerPay) -- voir
 * PaiementEnLigneService. L'application ne transmet que le plan choisi : le montant,
 * la référence et la confirmation sont entièrement déterminés côté serveur.
 */
class PaiementEnLigneController extends Controller
{
    public function payer(Request $request, Plan $plan, PaiementEnLigneService $paiements): JsonResponse
    {
        try {
            return response()->json($paiements->demarrer($request->user(), $plan), 201);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    /** Historique des paiements de l'utilisateur connecté -- champs en liste blanche. */
    public function historique(Request $request): JsonResponse
    {
        return response()->json(HistoriquePaiements::pour($request->user(), $request->integer('page', 1)));
    }

    /** Suivi par l'application : vérifie auprès de CamerPay, jamais sur sa parole. */
    public function statut(Request $request, string $reference, PaiementEnLigneService $paiements): JsonResponse
    {
        $tentative = TentativePaiement::where('user_id', $request->user()->id)
            ->where('reference_marchand', $reference)
            ->firstOrFail();

        $tentative = $paiements->verifier($tentative);
        $approuve = $tentative->paiement?->statut === Paiement::STATUT_APPROUVE;

        return response()->json([
            'statut' => $approuve ? TentativePaiement::CONFIRMEE : $tentative->statut,
            'message' => match (true) {
                $approuve => 'Paiement confirmé : votre abonnement est actif.',
                $tentative->statut === TentativePaiement::ECHOUEE => "Le paiement n'a pas abouti. Vous pouvez réessayer.",
                default => 'Paiement en cours de vérification...',
            },
        ]);
    }
}
