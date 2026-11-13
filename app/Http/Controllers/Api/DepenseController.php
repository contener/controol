<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepenseRequest;
use App\Http\Requests\UpdateDepenseRequest;
use App\Models\Depense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de DepenseController (web). Voir controol.md §5.21.
 */
class DepenseController extends Controller
{
    private const CATEGORIES = ['Loyer', 'Salaires', 'Achat marchandise', 'Transport', 'Facture (eau/électricité/internet)', 'Marketing', 'Autre'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Depense::class);

        $depenses = Depense::query()
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->string('debut')->toString(), fn ($q, $d) => $q->whereDate('date_depense', '>=', $d))
            ->when($request->string('fin')->toString(), fn ($q, $d) => $q->whereDate('date_depense', '<=', $d))
            ->latest('date_depense')
            ->paginate($request->integer('par_page', 15))
            ->withQueryString();

        return response()->json(['depenses' => $depenses, 'categories' => self::CATEGORIES]);
    }

    public function store(StoreDepenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $depense = Depense::create($data);

        return response()->json($depense, 201);
    }

    public function show(Depense $depense): JsonResponse
    {
        $this->authorize('view', $depense);

        return response()->json($depense);
    }

    public function update(UpdateDepenseRequest $request, Depense $depense): JsonResponse
    {
        $depense->update($request->validated());

        return response()->json($depense->fresh());
    }

    public function destroy(Depense $depense): JsonResponse
    {
        $this->authorize('delete', $depense);

        $depense->delete();

        return response()->json(['message' => 'Dépense supprimée.']);
    }
}
