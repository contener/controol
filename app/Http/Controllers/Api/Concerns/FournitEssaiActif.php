<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\EssaiStatut;
use App\Models\EssaiUtilisateur;
use App\Models\User;

/**
 * Même forme que DashboardController::essaiActif()/AbonnementController::index() (web)
 * -- bandeau "Essai Basique" du tableau de bord et de la page Abonnement côté mobile.
 */
trait FournitEssaiActif
{
    protected function essaiActif(User $user): ?array
    {
        $essai = EssaiUtilisateur::where('user_id', $user->id)->latest('id')->first();

        if (! $essai || $essai->statut() !== EssaiStatut::EnCours) {
            return null;
        }

        return [
            'jours_restants' => $essai->joursRestants(),
            'date_fin' => $essai->date_fin->format('d/m/Y'),
            'prix_promo' => (float) $essai->prix_promo,
            'prix_normal' => (float) $essai->plan->prix,
        ];
    }
}
