<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, Depense $depense): bool
    {
        return $user->id === $depense->boutique->user_id;
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function update(User $user, Depense $depense): bool
    {
        return $user->id === $depense->boutique->user_id;
    }

    public function delete(User $user, Depense $depense): bool
    {
        return $user->id === $depense->boutique->user_id;
    }
}
