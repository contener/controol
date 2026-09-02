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
        return $user->id === $campagneSociale->boutique->user_id;
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null && $user->planActif()?->publication_sociale === true;
    }

    public function update(User $user, CampagneSociale $campagneSociale): bool
    {
        return $user->id === $campagneSociale->boutique->user_id;
    }
}
