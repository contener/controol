<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDestinationSocialeRequest;
use App\Http\Requests\UpdateDestinationSocialeRequest;
use App\Models\CampagneSociale;
use App\Models\DestinationSociale;
use App\Services\LimiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DestinationSocialeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DestinationSociale::class);

        $boutique = $request->user()->currentBoutique;
        $plan = $request->user()->planActif();

        $campagneActive = CampagneSociale::where('statut', 'en_cours')
            ->latest()
            ->with('campagneDestinations.destination')
            ->first();

        $derniereCampagne = $campagneActive ?? CampagneSociale::latest()
            ->with('campagneDestinations.destination')
            ->first();

        return Inertia::render('Boutiques/PartageSocial/Index', [
            'destinations' => DestinationSociale::orderBy('ordre')->get(),
            'campagne' => $derniereCampagne,
            'autorise' => (bool) $plan?->publication_sociale,
            'peutAjouterDestination' => app(LimiteService::class)->peutAjouterDestination($request->user()),
            'limiteDestinations' => $plan?->limite_destinations_sociales,
            'boutique' => $boutique?->only(['id', 'nom', 'slug']),
        ]);
    }

    public function store(StoreDestinationSocialeRequest $request, LimiteService $limiteService): RedirectResponse
    {
        if (! $limiteService->peutAjouterDestination($request->user())) {
            return back()->with('flash_error', "Limite de destinations atteinte pour votre plan ({$request->user()->planActif()?->nom}).");
        }

        $data = $request->validated();
        $data['ordre'] = DestinationSociale::max('ordre') + 1;

        DestinationSociale::create($data);

        return back()->with('flash_success', 'Destination ajoutée avec succès.');
    }

    public function update(UpdateDestinationSocialeRequest $request, DestinationSociale $destinations_sociale): RedirectResponse
    {
        $destinations_sociale->update($request->validated());

        return back()->with('flash_success', 'Destination mise à jour avec succès.');
    }

    public function destroy(DestinationSociale $destinations_sociale): RedirectResponse
    {
        $this->authorize('delete', $destinations_sociale);

        $destinations_sociale->delete();

        return back()->with('flash_success', 'Destination supprimée avec succès.');
    }
}
