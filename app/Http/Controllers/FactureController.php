<?php

namespace App\Http\Controllers;

use App\Enums\FactureModele;
use App\Http\Requests\StoreFactureRequest;
use App\Http\Requests\UpdateFactureRequest;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use App\Policies\FacturePolicy;
use App\Services\FactureService;
use App\Services\LimiteService;
use App\Support\FactureApercuBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FactureController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Facture::class);

        $factures = Facture::query()
            ->with('client:id,nom')
            ->when($request->string('statut')->toString(), function ($query, $statut) {
                $query->where('statut', $statut);
            })
            ->when($request->integer('client_id'), function ($query, $clientId) {
                $query->where('client_id', $clientId);
            })
            ->latest('date_emission')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Factures/Index', [
            'factures' => $factures,
            'clients' => Client::orderBy('nom')->get(['id', 'nom']),
            'filtres' => $request->only(['statut', 'client_id']),
            'modeleLabels' => collect(FactureModele::cases())->mapWithKeys(fn (FactureModele $m) => [$m->value => $m->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Facture::class);

        return Inertia::render('Factures/Create', [
            'clients' => Client::orderBy('nom')->get(['id', 'nom', 'email', 'telephone', 'adresse', 'ville', 'pays', 'numero_fiscal', 'etiquette']),
            'produits' => Produit::orderBy('nom')->get(['id', 'nom', 'type', 'prix_vente', 'tva_taux', 'unite', 'gere_stock', 'quantite_stock']),
            'tauxTvaDefaut' => $this->tauxTvaDefaut($request),
            'boutique' => $request->user()->currentBoutique->only(['nom', 'logo_path', 'adresse', 'ville', 'pays', 'telephone', 'whatsapp', 'email', 'devise']),
            'modeles' => $this->modelesDisponibles($request->user()),
            'modeleInitial' => (int) $request->integer('modele', 1),
        ]);
    }

    public function store(StoreFactureRequest $request, FactureService $factureService, LimiteService $limiteService): RedirectResponse
    {
        if (! $limiteService->peutCreerFacture($request->user())) {
            return back()->with('flash_error', "Limite de factures atteinte pour votre plan ({$request->user()->planActif()?->nom}). Passez à un plan supérieur pour continuer à facturer.");
        }

        $facture = $factureService->creer(
            $request->validated(),
            $request->user(),
            $request->user()->currentBoutique->id,
        );

        return redirect()->route('factures.show', $facture)->with('flash_success', "Facture {$facture->numero} créée avec succès.");
    }

    public function show(Facture $facture, FactureApercuBuilder $apercuBuilder): Response
    {
        $this->authorize('view', $facture);

        return Inertia::render('Factures/Show', [
            'facture' => $facture->load(['client', 'lignes.produit', 'createur:id,name']),
            'apercu' => $apercuBuilder->construire($facture),
        ]);
    }

    public function edit(Facture $facture, Request $request): Response
    {
        $this->authorize('update', $facture);

        return Inertia::render('Factures/Edit', [
            'facture' => $facture->load('lignes'),
            'clients' => Client::orderBy('nom')->get(['id', 'nom', 'email', 'telephone', 'adresse', 'ville', 'pays', 'numero_fiscal', 'etiquette']),
            'produits' => Produit::orderBy('nom')->get(['id', 'nom', 'type', 'prix_vente', 'tva_taux', 'unite', 'gere_stock', 'quantite_stock']),
            'boutique' => $request->user()->currentBoutique->only(['nom', 'logo_path', 'adresse', 'ville', 'pays', 'telephone', 'whatsapp', 'email', 'devise']),
            'modeles' => $this->modelesDisponibles($request->user()),
        ]);
    }

    public function update(UpdateFactureRequest $request, Facture $facture, FactureService $factureService): RedirectResponse
    {
        $factureService->mettreAJour($facture, $request->validated(), $request->user());

        return redirect()->route('factures.show', $facture)->with('flash_success', 'Facture mise à jour avec succès.');
    }

    public function updateStatut(Request $request, Facture $facture, FactureService $factureService): RedirectResponse
    {
        $this->authorize('update', $facture);

        $data = $request->validate([
            'statut' => ['required', Rule::in(['brouillon', 'envoyee', 'payee', 'annulee'])],
        ]);

        $factureService->changerStatut($facture, $data['statut'], $request->user());

        return back()->with('flash_success', 'Statut de la facture mis à jour.');
    }

    public function destroy(Facture $facture, FactureService $factureService): RedirectResponse
    {
        $this->authorize('delete', $facture);

        $factureService->supprimer($facture, request()->user());

        return redirect()->route('factures.index')->with('flash_success', 'Facture supprimée avec succès.');
    }

    public function downloadPdf(Facture $facture, FactureApercuBuilder $apercuBuilder)
    {
        $this->authorize('view', $facture);

        $vue = sprintf('factures.pdf.modele-%02d', $facture->modele_id->value);

        $pdf = Pdf::loadView($vue, ['apercu' => $apercuBuilder->construire($facture)]);

        return $pdf->download("{$facture->numero}.pdf");
    }

    public function duplicate(Facture $facture, FactureService $factureService, LimiteService $limiteService): RedirectResponse
    {
        $this->authorize('view', $facture);
        $user = request()->user();

        if (! $limiteService->peutCreerFacture($user)) {
            return back()->with('flash_error', "Limite de factures atteinte pour votre plan ({$user->planActif()?->nom}). Passez à un plan supérieur pour continuer à facturer.");
        }

        $facture->loadMissing('lignes');

        $modeleId = $facture->modele_id->value;
        $modeleSubstitue = false;

        if (! app(FacturePolicy::class)->useModele($user, $modeleId)) {
            $modeleId = FactureModele::Standard->value;
            $modeleSubstitue = true;
        }

        $copie = $factureService->creer([
            'client_id' => $facture->client_id,
            'date_emission' => now()->toDateString(),
            'date_echeance' => null,
            'remise' => $facture->remise,
            'notes' => $facture->notes,
            'statut' => 'brouillon',
            'modele_id' => $modeleId,
            'lignes' => $facture->lignes->map(fn ($ligne) => [
                'produit_id' => $ligne->produit_id,
                'designation' => $ligne->designation,
                'description' => $ligne->description,
                'quantite' => $ligne->quantite,
                'prix_unitaire' => $ligne->prix_unitaire,
                'tva_taux' => $ligne->tva_taux,
                'remise_ligne' => $ligne->remise_ligne,
            ])->all(),
        ], $user, $user->currentBoutique->id);

        $message = $modeleSubstitue
            ? "Facture dupliquée en brouillon {$copie->numero} (modèle original indisponible sur votre plan actuel, remplacé par Standard)."
            : "Facture dupliquée en brouillon {$copie->numero}.";

        return redirect()->route('factures.edit', $copie)->with('flash_success', $message);
    }

    private function tauxTvaDefaut(Request $request): float
    {
        return (float) $request->user()->currentBoutique->taux_tva_defaut;
    }

    private function modelesDisponibles(User $user): array
    {
        return collect(FactureModele::cases())->map(fn (FactureModele $modele) => [
            'id' => $modele->value,
            'label' => $modele->label(),
            'gratuit' => $modele->estGratuit(),
            'autorise' => $modele->estAutorisePour($user),
        ])->all();
    }
}
