<?php

namespace App\Http\Controllers;

use App\Enums\FactureModele;
use App\Models\Facture;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FactureModeleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', Facture::class);

        $user = $request->user();

        return Inertia::render('Factures/Modeles/Index', [
            'modeles' => collect(FactureModele::cases())->map(fn (FactureModele $modele) => [
                'id' => $modele->value,
                'label' => $modele->label(),
                'gratuit' => $modele->estGratuit(),
                'autorise' => $modele->estAutorisePour($user),
            ])->all(),
        ]);
    }
}
