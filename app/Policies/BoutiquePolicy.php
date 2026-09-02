<?php

namespace App\Policies;

use App\Models\Boutique;
use App\Models\User;

class BoutiquePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Boutique $boutique): bool
    {
        return $user->id === $boutique->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Boutique $boutique): bool
    {
        return $user->id === $boutique->user_id;
    }

    public function delete(User $user, Boutique $boutique): bool
    {
        return $user->id === $boutique->user_id;
    }
}
