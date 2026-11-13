<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Services\LimiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de ClientController (web) -- mêmes FormRequests/Policies/Services,
 * réponses JSON au lieu de pages Inertia/redirections. Voir controol.md §5.21.
 */
class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
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
            ->when($request->string('etiquette')->toString(), fn ($query, $etiquette) => $query->where('etiquette', $etiquette))
            ->withCount([
                'factures as factures_count' => fn ($q) => $q->where('type', 'facture'),
                'factures as proformas_count' => fn ($q) => $q->where('type', 'proforma'),
                'factures as recus_count' => fn ($q) => $q->where('type', 'recu'),
            ])
            ->orderBy('nom')
            ->paginate($request->integer('par_page', 15))
            ->withQueryString();

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request, LimiteService $limiteService): JsonResponse
    {
        if (! $limiteService->peutCreerClient($request->user())) {
            return response()->json([
                'message' => "Limite de clients atteinte pour votre plan ({$request->user()->planActif()?->nom}).",
            ], 422);
        }

        $client = Client::create($request->validated());

        return response()->json($client, 201);
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json($client);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return response()->json($client->fresh());
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->json(['message' => 'Client supprimé.']);
    }
}
