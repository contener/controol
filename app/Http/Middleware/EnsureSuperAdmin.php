<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verrou serveur pour toute route réservée au Super Administrateur.
 *
 * Doit être combiné avec 'auth:sanctum' en amont (un visiteur non connecté est déjà
 * intercepté par ce middleware d'authentification). Ici on ne fait confiance qu'au rôle
 * persisté en base sur $request->user() — jamais à un en-tête, un paramètre de requête,
 * ou une valeur envoyée par le client : le frontend ne peut pas influencer ce contrôle.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isSuperAdmin()) {
            abort(403, "Cette section est réservée au Super Administrateur.");
        }

        return $next($request);
    }
}
