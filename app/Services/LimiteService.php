<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\DestinationSociale;
use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;

class LimiteService
{
    public function peutCreerBoutique(User $user): bool
    {
        $limite = $this->limite($user, 'limite_boutiques');

        return $limite === null || Boutique::where('user_id', $user->id)->count() < $limite;
    }

    public function peutCreerProduit(User $user): bool
    {
        $limite = $this->limite($user, 'limite_produits');

        return $limite === null || $this->compterSurBoutiques($user, Produit::class) < $limite;
    }

    public function peutCreerClient(User $user): bool
    {
        $limite = $this->limite($user, 'limite_clients');

        return $limite === null || $this->compterSurBoutiques($user, Client::class) < $limite;
    }

    public function peutCreerFacture(User $user): bool
    {
        $limite = $this->limite($user, 'limite_factures');

        return $limite === null || $this->compterSurBoutiques($user, Facture::class) < $limite;
    }

    public function peutActiverStockSupplementaire(User $user): bool
    {
        $limite = $this->limite($user, 'limite_stocks');

        if ($limite === null) {
            return true;
        }

        $boutiqueIds = Boutique::where('user_id', $user->id)->pluck('id');
        $count = Produit::withoutGlobalScopes()
            ->whereIn('boutique_id', $boutiqueIds)
            ->where('gere_stock', true)
            ->count();

        return $count < $limite;
    }

    public function peutAjouterDestination(User $user): bool
    {
        $plan = $user->planActif();

        if (! $plan || ! $plan->publication_sociale) {
            return false;
        }

        $limite = $plan->limite_destinations_sociales;

        return $limite === null || $this->compterSurBoutiques($user, DestinationSociale::class) < $limite;
    }

    public function usage(User $user): array
    {
        $plan = $user->planActif();

        return [
            'plan' => $plan?->nom,
            'boutiques' => [
                'utilise' => Boutique::where('user_id', $user->id)->count(),
                'limite' => $plan?->limite_boutiques,
            ],
            'produits' => [
                'utilise' => $this->compterSurBoutiques($user, Produit::class),
                'limite' => $plan?->limite_produits,
            ],
            'clients' => [
                'utilise' => $this->compterSurBoutiques($user, Client::class),
                'limite' => $plan?->limite_clients,
            ],
            'factures' => [
                'utilise' => $this->compterSurBoutiques($user, Facture::class),
                'limite' => $plan?->limite_factures,
            ],
        ];
    }

    private function limite(User $user, string $champ): ?int
    {
        $plan = $user->planActif();

        // Aucun abonnement actif retrouvé : on se rabat sur les limites du plan gratuit par sécurité.
        if (! $plan) {
            return 0;
        }

        return $plan->{$champ};
    }

    private function compterSurBoutiques(User $user, string $modelClass): int
    {
        $boutiqueIds = Boutique::where('user_id', $user->id)->pluck('id');

        return $modelClass::withoutGlobalScopes()->whereIn('boutique_id', $boutiqueIds)->count();
    }
}
