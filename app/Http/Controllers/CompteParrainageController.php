<?php

namespace App\Http\Controllers;

use App\Models\CommissionParrainage;
use App\Models\Paiement;
use App\Services\ParrainageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompteParrainageController extends Controller
{
    public function index(Request $request, ParrainageService $parrainage): Response
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

                $commission = (float) CommissionParrainage::where('filleul_id', $filleul->id)->actives()->sum('montant_commission');

                return [
                    'nom' => $filleul->name,
                    'date_inscription' => $filleul->created_at,
                    'statut_paiement' => $statutPaiement,
                    'commission' => $commission,
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

        return Inertia::render('Compte/Parrainage', [
            'lienParrainage' => route('register', ['ref' => $code]),
            'statistiques' => $parrainage->statistiques($user),
            'filleuls' => $filleuls,
            'historique' => $historique,
        ]);
    }
}
