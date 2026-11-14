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

    /**
     * Basculer sur une boutique : propriétaire OU collaborateur de cette boutique
     * (Boutique::estGereePar()) -- 'view' (propriétaire seul) empêchait un
     * collaborateur de choisir la boutique où il travaille. Les permissions de
     * chaque fonctionnalité restent vérifiées ensuite (autoriseCollaborateurPour()).
     */
    public function basculer(User $user, Boutique $boutique): bool
    {
        return $boutique->estGereePar($user);
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
