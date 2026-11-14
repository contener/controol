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

    /**
     * Un visiteur bloqué par la boutique ne peut plus répondre, même dans un fil
     * existant de "Mes conversations" (audit sécurité V-04) -- même règle que
     * GuestConversationController et PublicBoutiqueController::envoyerMessage().
     */
    public function repondreCommeVisiteur(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->visiteur_user_id
            && $conversation->statut !== Conversation::STATUT_BLOQUEE;
    }
}
