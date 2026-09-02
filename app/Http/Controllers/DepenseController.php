<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepenseRequest;
use App\Http\Requests\UpdateDepenseRequest;
use App\Models\Depense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepenseController extends Controller
{
    private const CATEGORIES = ['Loyer', 'Salaires', 'Achat marchandise', 'Transport', 'Facture (eau/électricité/internet)', 'Marketing', 'Autre'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Depense::class);

        $depenses = Depense::query()
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->string('debut')->toString(), fn ($q, $d) => $q->whereDate('date_depense', '>=', $d))
            ->when($request->string('fin')->toString(), fn ($q, $d) => $q->whereDate('date_depense', '<=', $d))
            ->latest('date_depense')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Depenses/Index', [
            'depenses' => $depenses,
            'categories' => self::CATEGORIES,
            'filtres' => $request->only(['categorie', 'debut', 'fin']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Depense::class);

        return Inertia::render('Depenses/Create', [
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(StoreDepenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        Depense::create($data);

        return redirect()->route('depenses.index')->with('flash_success', 'Dépense enregistrée avec succès.');
    }

    public function edit(Depense $depense): Response
    {
        $this->authorize('update', $depense);

        return Inertia::render('Depenses/Edit', [
            'depense' => $depense,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(UpdateDepenseRequest $request, Depense $depense): RedirectResponse
    {
        $depense->update($request->validated());

        return redirect()->route('depenses.index')->with('flash_success', 'Dépense mise à jour avec succès.');
    }

    public function destroy(Depense $depense): RedirectResponse
    {
        $this->authorize('delete', $depense);

        $depense->delete();

        return redirect()->route('depenses.index')->with('flash_success', 'Dépense supprimée avec succès.');
    }
}
