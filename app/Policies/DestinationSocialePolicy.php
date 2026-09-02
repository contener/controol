<?php

namespace App\Policies;

use App\Models\DestinationSociale;
use App\Models\User;

class DestinationSocialePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, DestinationSociale $destinationSociale): bool
    {
        return $user->id === $destinationSociale->boutique->user_id;
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function update(User $user, DestinationSociale $destinationSociale): bool
    {
        return $user->id === $destinationSociale->boutique->user_id;
    }

    public function delete(User $user, DestinationSociale $destinationSociale): bool
    {
        return $user->id === $destinationSociale->boutique->user_id;
    }
}
