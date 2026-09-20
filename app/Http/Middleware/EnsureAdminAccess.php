<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porte d'entrée de tout /admin/* : bloque les utilisateurs normaux ET les
 * administrateurs désactivés (est_actif=false). Ne remplace pas 'super_admin' — les
 * routes /admin/administrateurs/* restent en plus protégées par ce middleware exclusif
 * (voir routes/web.php), aucune permission ne donnant accès à la gestion des admins.
 *
 * Exige en plus la double authentification confirmée (2FA) : un compte admin/super
 * admin a accès à l'ensemble de la plateforme, un mot de passe seul ne doit jamais
 * suffire à y entrer. two_factor_confirmed_at (pas seulement two_factor_secret) est le
 * seul indicateur fiable : il n'est posé qu'une fois la configuration réellement
 * vérifiée par un code OTP valide, jamais pendant une configuration abandonnée en
 * cours de route.
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessAdminSpace()) {
            abort(403, "Vous n'avez pas l'autorisation d'accéder à l'espace d'administration.");
        }

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('profile.show')->with(
                'flash_error',
                "L'accès à l'administration exige l'activation de la double authentification (2FA). Activez-la ci-dessous pour continuer."
            );
        }

        return $next($request);
    }
}
