<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->boutique->user_id;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->boutique->user_id;
    }

    public function updateStatut(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->boutique->user_id;
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function repondreCommeVisiteur(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->visiteur_user_id;
    }
}
