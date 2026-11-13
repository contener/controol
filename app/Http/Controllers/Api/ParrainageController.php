<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionParrainage;
use App\Models\Paiement;
use App\Services\ParrainageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de CompteParrainageController -- même lien, mêmes statistiques
 * (ParrainageService), mêmes filleuls et historique de commissions.
 */
class ParrainageController extends Controller
{
    public function index(Request $request, ParrainageService $parrainage): JsonResponse
    {
        $user = $request->user();
        $code = $parrainage->assurerCodeParrainage($user);

        $filleuls = $user->filleuls()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'created_at'])
            ->map(function ($filleul) {
                $paiements = Paiement::where('user_id', $filleul->id)->pluck('statut');

                $statutPaiement = $paiements->isEmpty()
                    ? 'non_paye'
                    : ($paiements->contains(Paiement::STATUT_APPROUVE) ? 'valide' : 'en_attente');

                return [
                    'nom' => $filleul->name,
                    'date_inscription' => $filleul->created_at,
                    'statut_paiement' => $statutPaiement,
                    'commission' => (float) CommissionParrainage::where('filleul_id', $filleul->id)->actives()->sum('montant_commission'),
                ];
            });

        $historique = $user->commissionsGagnees()
            ->with('filleul:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (CommissionParrainage $c) => [
                'id' => $c->id,
                'date' => $c->created_at,
                'filleul' => $c->filleul?->name,
                'montant_eligible' => (float) $c->montant_eligible,
                'taux' => (float) $c->taux,
                'montant_commission' => (float) $c->montant_commission,
                'statut' => $c->statut,
            ]);

        return response()->json([
            'lien_parrainage' => route('register', ['ref' => $code]),
            'statistiques' => $parrainage->statistiques($user),
            'filleuls' => $filleuls,
            'historique' => $historique,
        ]);
    }
}
