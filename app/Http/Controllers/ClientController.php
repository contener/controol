<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Services\LimiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%")
                        ->orWhere('telephone', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('etiquette')->toString(), function ($query, $etiquette) {
                $query->where('etiquette', $etiquette);
            })
            ->withCount('factures')
            ->orderBy('nom')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filtres' => $request->only(['recherche', 'etiquette']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('Clients/Create');
    }

    public function store(StoreClientRequest $request, LimiteService $limiteService): RedirectResponse
    {
        if (! $limiteService->peutCreerClient($request->user())) {
            return back()->with('flash_error', "Limite de clients atteinte pour votre plan ({$request->user()->planActif()?->nom}). Passez à un plan supérieur pour en ajouter davantage.");
        }

        Client::create($request->validated());

        return redirect()->route('clients.index')->with('flash_success', 'Client créé avec succès.');
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'client' => $client,
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('clients.index')->with('flash_success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()->route('clients.index')->with('flash_success', 'Client supprimé avec succès.');
    }
}
