<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionParrainage;
use App\Models\Paiement;
use App\Models\ReglementParrainage;
use App\Models\User;
use App\Services\ParrainageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Toutes les actions sont protégées par EnsureAdminAccess (tout /admin/*) + une
 * permission granulaire par route ('parrainage.voir'/'gerer', voir routes/web.php et
 * App\Support\AdminPermissions) — un Super Admin passe toujours.
 */
class ParrainageController extends Controller
{
    public function index(Request $request): Response
    {
        $parrains = User::whereNotNull('code_parrainage')
            ->whereHas('filleuls')
            ->withCount('filleuls')
            ->when($request->string('recherche')->toString(), function ($q, $recherche) {
                $q->where(function ($qq) use ($recherche) {
                    $qq->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('debut')->toString(), fn ($q, $debut) => $q->whereDate('created_at', '>=', $debut))
            ->when($request->string('fin')->toString(), fn ($q, $fin) => $q->whereDate('created_at', '<=', $fin))
            ->orderByDesc('filleuls_count')
            ->paginate(20)
            ->withQueryString();

        $parrainageService = app(ParrainageService::class);
        $parrains->getCollection()->transform(fn (User $parrain) => [
            'id' => $parrain->id,
            'name' => $parrain->name,
            'email' => $parrain->email,
            'filleuls_count' => $parrain->filleuls_count,
            'solde_disponible' => $parrainageService->soldeDisponible($parrain),
        ]);

        $filleulIdsGlobal = User::whereNotNull('parrain_id')->pluck('id');

        return Inertia::render('Admin/Parrainage/Index', [
            'parrains' => $parrains,
            'filtres' => $request->only(['recherche', 'debut', 'fin']),
            'statistiques' => [
                'total_parrains_actifs' => User::whereNotNull('parrain_id')->distinct('parrain_id')->count('parrain_id'),
                'comptes_crees' => $filleulIdsGlobal->count(),
                'paiements_commences' => Paiement::whereIn('user_id', $filleulIdsGlobal)->distinct('user_id')->count('user_id'),
                'paiements_valides' => Paiement::whereIn('user_id', $filleulIdsGlobal)->where('statut', Paiement::STATUT_APPROUVE)->distinct('user_id')->count('user_id'),
                'commissions_disponibles' => round((float) CommissionParrainage::actives()->sum('montant_commission') - (float) ReglementParrainage::sum('montant'), 2),
                'commissions_deja_payees' => (float) ReglementParrainage::sum('montant'),
            ],
        ]);
    }

    public function show(User $utilisateur, ParrainageService $parrainage): Response
    {
        $filleuls = $utilisateur->filleuls()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(function ($filleul) {
                $paiements = Paiement::where('user_id', $filleul->id)->pluck('statut');

                return [
                    'id' => $filleul->id,
                    'nom' => $filleul->name,
                    'email' => $filleul->email,
                    'date_inscription' => $filleul->created_at,
                    'statut_paiement' => $paiements->isEmpty()
                        ? 'non_paye'
                        : ($paiements->contains(Paiement::STATUT_APPROUVE) ? 'valide' : 'en_attente'),
                ];
            });

        $commissions = $utilisateur->commissionsGagnees()
            ->with('filleul:id,name')
            ->latest()
            ->get();

        $reglements = $utilisateur->reglementsParrainage()
            ->with('traiteur:id,name')
            ->latest()
            ->get();

        return Inertia::render('Admin/Parrainage/Show', [
            'parrain' => $utilisateur->only(['id', 'name', 'email']),
            'filleuls' => $filleuls,
            'commissions' => $commissions,
            'reglements' => $reglements,
            'statistiques' => $parrainage->statistiques($utilisateur),
        ]);
    }

    public function enregistrerReglement(Request $request, User $utilisateur, ParrainageService $parrainage): RedirectResponse
    {
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'methode_paiement' => ['nullable', 'string', 'max:100'],
            'reference_transaction' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $parrainage->enregistrerReglement($utilisateur, (float) $data['montant'], $data, $request->user(), $request);

        return back()->with('flash_success', "Règlement de {$data['montant']} FCFA enregistré pour {$utilisateur->name}.");
    }

    public function annulerCommission(Request $request, CommissionParrainage $commission, ParrainageService $parrainage): RedirectResponse
    {
        $data = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        $parrainage->annulerCommission($commission, $request->user(), $data['motif'], $request);

        return back()->with('flash_success', 'Commission annulée.');
    }
}
