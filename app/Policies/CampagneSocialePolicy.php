<?php

namespace App\Policies;

use App\Models\CampagneSociale;
use App\Models\User;

class CampagneSocialePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, CampagneSociale $campagneSociale): bool
    {
        return $campagneSociale->boutique->estGereePar($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null && $user->planActif()?->publication_sociale === true;
    }

    public function update(User $user, CampagneSociale $campagneSociale): bool
    {
        return $campagneSociale->boutique->estGereePar($user);
    }
}
