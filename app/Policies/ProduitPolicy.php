<?php

namespace App\Policies;

use App\Models\Produit;
use App\Models\User;

class ProduitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique?->autoriseProduits($user) ?? false;
    }

    public function view(User $user, Produit $produit): bool
    {
        return $produit->boutique->autoriseProduits($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique?->autoriseProduits($user) ?? false;
    }

    public function update(User $user, Produit $produit): bool
    {
        return $produit->boutique->autoriseProduits($user);
    }

    public function delete(User $user, Produit $produit): bool
    {
        return $produit->boutique->autoriseProduits($user);
    }
}
