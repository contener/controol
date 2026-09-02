<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     *
     * Les boutiques de l'utilisateur (et toutes leurs données : clients, produits,
     * factures...) sont supprimées automatiquement par les contraintes ON DELETE
     * CASCADE définies en base, en cascade depuis `boutiques.user_id`.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }
}
