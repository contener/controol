<?php

namespace App\Http\Controllers;

use App\Http\Requests\MouvementCaisseManuelRequest;
use App\Services\CaisseService;
use App\Support\JournalCaisse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caisse de la boutique courante (2026-11-17) : solde, ajout et retrait d'argent,
 * journal des mouvements (ventes encaissées, dépenses, opérations manuelles). Même
 * accès que les Dépenses. Voir CaisseService.
 */
class CaisseController extends Controller
{
    public function index(Request $request): Response
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique?->autoriseDepenses($request->user()), 403);

        return Inertia::render('Caisse/Index', JournalCaisse::pour($boutique, $request));
    }

    public function apport(MouvementCaisseManuelRequest $request, CaisseService $caisse): RedirectResponse
    {
        $caisse->apport($request->user()->currentBoutique, $request->user(), (float) $request->input('montant'), $request->string('motif')->toString());

        return back()->with('flash_success', 'Argent ajouté à la caisse.');
    }

    public function retrait(MouvementCaisseManuelRequest $request, CaisseService $caisse): RedirectResponse
    {
        $caisse->retrait($request->user()->currentBoutique, $request->user(), (float) $request->input('montant'), $request->string('motif')->toString());

        return back()->with('flash_success', 'Argent retiré de la caisse.');
    }
}
