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
 */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->canAccessAdminSpace()) {
            abort(403, "Vous n'avez pas l'autorisation d'accéder à l'espace d'administration.");
        }

        return $next($request);
    }
}
