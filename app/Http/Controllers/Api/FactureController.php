<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFactureRequest;
use App\Http\Requests\UpdateFactureRequest;
use App\Models\Client;
use App\Models\Facture;
use App\Services\FactureService;
use App\Services\LimiteService;
use App\Support\FactureApercuBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Équivalent API de FactureController (web) -- même FactureService pour toute la
 * logique métier (numérotation, mouvements de stock, totaux, avance/reste des
 * Reçus...), jamais réimplémentée ici. Le choix du modèle PDF et le téléchargement
 * du PDF restent web-only pour l'instant (voir controol.md §5.21) : l'app mobile
 * est faite pour saisir/consulter, pas pour produire un document imprimable.
 */
class FactureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Facture::class);

        $factures = Facture::query()
            ->with('client:id,nom')
            ->when($request->string('statut')->toString(), fn ($query, $statut) => $query->where('statut', $statut))
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->when($request->integer('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->latest('date_emission')
            ->paginate($request->integer('par_page', 15))
            ->withQueryString();

        $factures->getCollection()->transform(function (Facture $facture) {
            $facture->est_modifiable = $facture->estModifiable();
            $facture->est_supprimable = $facture->estSupprimable();

            return $facture;
        });

        return response()->json($factures);
    }

    public function store(StoreFactureRequest $request, FactureService $factureService, LimiteService $limiteService): JsonResponse
    {
        if (! $limiteService->peutCreerFacture($request->user())) {
            return response()->json([
                'message' => "Limite de factures atteinte pour votre plan ({$request->user()->planActif()?->nom}).",
            ], 422);
        }

        $facture = $factureService->creer(
            $request->validated(),
            $request->user(),
            $request->user()->currentBoutique->id,
        );

        return response()->json($facture, 201);
    }

    public function show(Facture $facture, FactureApercuBuilder $apercuBuilder): JsonResponse
    {
        $this->authorize('view', $facture);

        $facture->load(['client', 'lignes.produit', 'createur:id,name']);
        $facture->est_modifiable = $facture->estModifiable();
        $facture->est_supprimable = $facture->estSupprimable();

        return response()->json([
            'facture' => $facture,
            // Mêmes totaux/avance/reste déjà calculés pour le web (apercu PDF/HTML) --
            // jamais recalculés une troisième fois côté Dart.
            'apercu' => $apercuBuilder->construire($facture),
        ]);
    }

    public function update(UpdateFactureRequest $request, Facture $facture, FactureService $factureService): JsonResponse
    {
        $facture = $factureService->mettreAJour($facture, $request->validated(), $request->user());

        return response()->json($facture);
    }

    public function updateStatut(Request $request, Facture $facture, FactureService $factureService): JsonResponse
    {
        $this->authorize('update', $facture);

        $data = $request->validate([
            'statut' => ['required', Rule::in(['brouillon', 'envoyee', 'payee', 'annulee'])],
        ]);

        $facture = $factureService->changerStatut($facture, $data['statut'], $request->user());

        return response()->json($facture);
    }

    public function destroy(Facture $facture, FactureService $factureService): JsonResponse
    {
        $this->authorize('delete', $facture);

        $factureService->supprimer($facture, request()->user());

        return response()->json(['message' => 'Facture supprimée.']);
    }

    /**
     * Données nécessaires pour construire le formulaire de saisie côté mobile
     * (sélection client/produit) -- évite à l'app de deviner la forme attendue par
     * StoreFactureRequest à partir de /api/clients et /api/produits séparément.
     */
    public function contexteCreation(Request $request): JsonResponse
    {
        $this->authorize('create', Facture::class);

        return response()->json([
            'clients' => Client::orderBy('nom')->get(['id', 'nom', 'email', 'telephone', 'adresse', 'ville', 'pays', 'code_postal', 'numero_fiscal', 'rccm', 'etiquette']),
            'produits' => \App\Models\Produit::orderBy('nom')->get(['id', 'nom', 'type', 'prix_vente', 'tva_taux', 'unite', 'gere_stock', 'quantite_stock']),
            'taux_tva_defaut' => $request->user()->currentBoutique->taux_tva_defaut,
        ]);
    }
}
