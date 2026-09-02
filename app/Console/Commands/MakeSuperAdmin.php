<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    /**
     * Seul moyen de créer/promouvoir un Super Administrateur : exécution manuelle en
     * ligne de commande par un opérateur ayant accès au serveur. Aucune route HTTP,
     * aucun formulaire, aucun endpoint API ne permet ce changement de rôle.
     */
    protected $signature = 'user:make-super-admin {email : Adresse email d\'un compte existant}';

    protected $description = "Promeut un utilisateur existant au rôle Super Administrateur";

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun utilisateur trouvé avec l'adresse email : {$email}");

            return self::FAILURE;
        }

        if ($user->isSuperAdmin()) {
            $this->info("{$user->name} ({$email}) est déjà Super Administrateur.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Promouvoir {$user->name} ({$email}) au rôle Super Administrateur ?")) {
            $this->info('Annulé.');

            return self::SUCCESS;
        }

        $user->forceFill(['role' => User::ROLE_SUPER_ADMIN])->save();

        $this->info("{$user->name} ({$email}) est maintenant Super Administrateur.");

        return self::SUCCESS;
    }
}
