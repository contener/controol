<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $client->boutique->estGereePar($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function update(User $user, Client $client): bool
    {
        return $client->boutique->estGereePar($user);
    }

    public function delete(User $user, Client $client): bool
    {
        return $client->boutique->estGereePar($user);
    }
}
