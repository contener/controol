<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MouvementCaisseManuelRequest;
use App\Services\CaisseService;
use App\Support\JournalCaisse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Équivalent API de CaisseController (application mobile). */
class CaisseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique?->autoriseDepenses($request->user()), 403, "Vous n'avez pas accès à la caisse de cette boutique.");

        return response()->json(JournalCaisse::pour($boutique, $request));
    }

    public function apport(MouvementCaisseManuelRequest $request, CaisseService $caisse): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        $caisse->apport($boutique, $request->user(), (float) $request->input('montant'), $request->string('motif')->toString());

        return response()->json(['message' => 'Argent ajouté à la caisse.', 'solde' => $caisse->solde($boutique->id)], 201);
    }

    public function retrait(MouvementCaisseManuelRequest $request, CaisseService $caisse): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        $caisse->retrait($boutique, $request->user(), (float) $request->input('montant'), $request->string('motif')->toString());

        return response()->json(['message' => 'Argent retiré de la caisse.', 'solde' => $caisse->solde($boutique->id)], 201);
    }
}
