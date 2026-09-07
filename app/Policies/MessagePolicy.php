<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function update(User $user, Message $message): bool
    {
        return $user->id === $message->boutique->user_id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $user->id === $message->boutique->user_id;
    }
}
