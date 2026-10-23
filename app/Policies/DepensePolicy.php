<?php

namespace App\Policies;

use App\Models\Depense;
use App\Models\User;

class DepensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique?->autoriseDepenses($user) ?? false;
    }

    public function view(User $user, Depense $depense): bool
    {
        return $depense->boutique->autoriseDepenses($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique?->autoriseDepenses($user) ?? false;
    }

    public function update(User $user, Depense $depense): bool
    {
        return $depense->boutique->autoriseDepenses($user);
    }

    public function delete(User $user, Depense $depense): bool
    {
        return $depense->boutique->autoriseDepenses($user);
    }
}
