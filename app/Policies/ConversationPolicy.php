<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique?->autoriseMessagerie($user) ?? false;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->boutique->autoriseMessagerie($user);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $conversation->boutique->autoriseMessagerie($user);
    }

    public function updateStatut(User $user, Conversation $conversation): bool
    {
        return $conversation->boutique->autoriseMessagerie($user);
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique?->autoriseMessagerie($user) ?? false;
    }

    public function repondreCommeVisiteur(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->visiteur_user_id;
    }
}
