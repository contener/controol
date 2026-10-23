<?php

namespace App\Policies;

use App\Models\Produit;
use App\Models\User;

class ProduitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, Produit $produit): bool
    {
        return $produit->boutique->estGereePar($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function update(User $user, Produit $produit): bool
    {
        return $produit->boutique->estGereePar($user);
    }

    public function delete(User $user, Produit $produit): bool
    {
        return $produit->boutique->estGereePar($user);
    }
}
