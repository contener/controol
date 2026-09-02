<?php

namespace Tests;

use App\Models\Boutique;
use App\Models\Plan;
use App\Models\User;

trait CreatesBoutique
{
    protected function creerUtilisateurAvecBoutique(string $planCode = 'pro', array $boutiqueAttributs = []): User
    {
        $user = User::factory()->create();

        $plan = Plan::firstOrCreate(['code' => $planCode], [
            'nom' => ucfirst($planCode),
            'prix' => 0,
            'limite_boutiques' => null,
            'limite_produits' => null,
            'limite_clients' => null,
            'limite_factures' => null,
            'limite_stocks' => null,
        ]);

        $user->abonnements()->create([
            'plan_id' => $plan->id,
            'statut' => 'actif',
            'date_debut' => now(),
        ]);

        $boutique = Boutique::create(array_merge([
            'user_id' => $user->id,
            'nom' => 'Boutique de '.$user->name,
            'slug' => 'boutique-'.$user->id.'-'.uniqid(),
            'devise' => 'XAF',
            'taux_tva_defaut' => 19.25,
        ], $boutiqueAttributs));

        $user->switchBoutique($boutique);

        return $user->fresh();
    }
}
