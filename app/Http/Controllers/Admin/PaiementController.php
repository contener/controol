<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejeterPaiementRequest;
use App\Models\Paiement;
use App\Models\Plan;
use App\Services\PaiementValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Toutes les actions de ce contrôleur sont protégées par le middleware 'super_admin'
 * (voir routes/web.php) : aucune méthode ici ne doit jamais être atteignable sans que
 * ce middleware ait déjà validé Auth::user()->isSuperAdmin(). Ne pas retirer la
 * protection de route en supposant qu'un contrôle ici suffirait.
 */
class PaiementController extends Controller
{
    public function index(Request $request): Response
    {
        $paiements = Paiement::query()
            ->with(['user:id,name,email,telephone', 'abonnement.plan', 'validateur:id,name'])
            ->when($request->string('statut')->toString(), fn ($q, $statut) => $q->where('statut', $statut))
            ->when($request->string('moyen_paiement')->toString(), fn ($q, $moyen) => $q->where('moyen_paiement', $moyen))
            ->when($request->integer('plan_id'), fn ($q, $planId) => $q->whereHas('abonnement', fn ($sq) => $sq->where('plan_id', $planId)))
            ->when($request->string('recherche')->toString(), function ($q, $recherche) {
                $q->whereHas('user', function ($sq) use ($recherche) {
                    $sq->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('debut')->toString(), fn ($q, $debut) => $q->whereDate('created_at', '>=', $debut))
            ->when($request->string('fin')->toString(), fn ($q, $fin) => $q->whereDate('created_at', '<=', $fin))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Paiements/Index', [
            'paiements' => $paiements,
            'plans' => Plan::orderBy('ordre')->get(['id', 'nom']),
            'filtres' => $request->only(['statut', 'moyen_paiement', 'plan_id', 'recherche', 'debut', 'fin']),
        ]);
    }

    public function show(Paiement $paiement): Response
    {
        return Inertia::render('Admin/Paiements/Show', [
            'paiement' => $paiement->load(['user', 'abonnement.plan', 'validateur:id,name', 'audits.admin:id,name']),
        ]);
    }

    public function approuver(Request $request, Paiement $paiement, PaiementValidationService $service): RedirectResponse
    {
        if (! $paiement->estEnAttente()) {
            return back()->with('flash_error', 'Ce paiement a déjà été traité.');
        }

        $service->approuver($paiement, $request->user(), $request);

        return back()->with('flash_success', 'Paiement approuvé, abonnement activé.');
    }

    public function rejeter(RejeterPaiementRequest $request, Paiement $paiement, PaiementValidationService $service): RedirectResponse
    {
        if (! $paiement->estEnAttente()) {
            return back()->with('flash_error', 'Ce paiement a déjà été traité.');
        }

        $service->rejeter($paiement, $request->user(), $request->validated('motif'), $request);

        return back()->with('flash_success', 'Paiement rejeté.');
    }
}
