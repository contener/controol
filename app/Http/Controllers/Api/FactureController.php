<?php

namespace App\Http\Controllers\Api;

use App\Enums\FactureModele;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFactureRequest;
use App\Http\Requests\UpdateFactureRequest;
use App\Models\Client;
use App\Models\Facture;
use App\Services\FactureService;
use App\Services\LimiteService;
use App\Support\FactureApercuBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Équivalent API de FactureController (web) -- même FactureService pour toute la
 * logique métier (numérotation, mouvements de stock, totaux, avance/reste des
 * Reçus...), jamais réimplémentée ici. Le PDF (pdf()) est généré avec exactement
 * la même vue/le même FactureApercuBuilder que FactureController::downloadPdf()
 * (web). Modèles visuels (2026-11-14) : mêmes 10 modèles et même règle d'accès
 * (FactureModele::estAutorisePour) que le web, aperçus rendus par les mêmes vues PDF.
 */
class FactureController extends Controller
{
    /** Galerie de modèles -- même liste que FactureController::modelesDisponibles() (web). */
    public function modeles(Request $request): JsonResponse
    {
        $this->authorize('create', Facture::class);

        return response()->json(['modeles' => $this->modelesDisponibles($request->user())]);
    }

    /**
     * Aperçu PDF d'un modèle avec l'en-tête réel de la boutique courante et des
     * données d'exemple (miniatures de la galerie). Visible même pour un modèle
     * verrouillé, comme les miniatures du site -- seule son utilisation est réservée.
     */
    public function apercuModele(Request $request, int $modele, FactureApercuBuilder $apercuBuilder)
    {
        $this->authorize('create', Facture::class);
        abort_unless(FactureModele::tryFrom($modele) !== null, 404);

        return $this->reponsePdf(
            sprintf('factures.pdf.modele-%02d', $modele),
            $apercuBuilder->exemple($request->user()->currentBoutique),
            'apercu-modele.pdf',
        );
    }

    /**
     * Aperçu PDF de la facture en cours de saisie, dans le modèle choisi -- rendu
     * EXACT du document final : FactureService::creer() est exécuté avec les données
     * du formulaire (mêmes règles StoreFactureRequest, y compris le droit d'utiliser
     * ce modèle), le PDF est construit, puis TOUT est annulé (DB::rollBack) -- aucune
     * facture, aucun numéro consommé, aucun mouvement de stock n'est conservé.
     */
    public function apercuBrouillon(StoreFactureRequest $request, FactureService $factureService, FactureApercuBuilder $apercuBuilder)
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            $facture = $factureService->creer($request->validated(), $user, $user->currentBoutique->id);
            $vue = sprintf('factures.pdf.modele-%02d', $facture->modele_id->value);
            $apercu = $apercuBuilder->construire($facture);
        } finally {
            DB::rollBack();
        }

        return $this->reponsePdf($vue, $apercu, 'apercu-facture.pdf');
    }

    private function reponsePdf(string $vue, array $apercu, string $nomFichier)
    {
        return response(Pdf::loadView($vue, ['apercu' => $apercu])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nomFichier.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function modelesDisponibles(\App\Models\User $user): array
    {
        return collect(FactureModele::cases())->map(fn (FactureModele $modele) => [
            'id' => $modele->value,
            'label' => $modele->label(),
            'gratuit' => $modele->estGratuit(),
            'autorise' => $modele->estAutorisePour($user),
        ])->all();
    }
    /** Même règles que FactureController::duplicate() (web) via FactureService::dupliquer(). */
    public function dupliquer(Request $request, Facture $facture, FactureService $factureService, LimiteService $limiteService): JsonResponse
    {
        $this->authorize('view', $facture);
        $user = $request->user();

        if (! $limiteService->peutCreerFacture($user)) {
            return response()->json([
                'message' => "Limite de factures atteinte pour votre plan ({$user->planActif()?->nom}). Passez à un plan supérieur pour continuer à facturer.",
            ], 422);
        }

        ['copie' => $copie, 'modele_substitue' => $substitue] = $factureService->dupliquer($facture, $user);

        return response()->json([
            'message' => $substitue
                ? "{$copie->type->label()} dupliquée en brouillon {$copie->numero} (modèle original indisponible sur votre plan actuel, remplacé par Standard)."
                : "{$copie->type->label()} dupliquée en brouillon {$copie->numero}.",
            'facture' => $copie,
        ], 201);
    }

    public function pdf(Facture $facture, FactureApercuBuilder $apercuBuilder)
    {
        $this->authorize('view', $facture);

        $vue = sprintf('factures.pdf.modele-%02d', $facture->modele_id->value);

        return Pdf::loadView($vue, ['apercu' => $apercuBuilder->construire($facture)])
            ->download("{$facture->numero}.pdf");
    }

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
            'modeles' => $this->modelesDisponibles($request->user()),
            'boutique' => $request->user()->currentBoutique->only(['id', 'nom', 'nui', 'devise']),
            'peut_modifier_boutique' => $request->user()->can('update', $request->user()->currentBoutique),
        ]);
    }
}
