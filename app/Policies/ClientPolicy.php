<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique?->autoriseClients($user) ?? false;
    }

    public function view(User $user, Client $client): bool
    {
        return $client->boutique->autoriseClients($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique?->autoriseClients($user) ?? false;
    }

    public function update(User $user, Client $client): bool
    {
        return $client->boutique->autoriseClients($user);
    }

    public function delete(User $user, Client $client): bool
    {
        return $client->boutique->autoriseClients($user);
    }
}
