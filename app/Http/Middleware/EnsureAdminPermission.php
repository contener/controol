<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vérifie une permission granulaire précise (ex. 'paiements.valider'), en plus de
 * EnsureAdminAccess. Un Super Admin passe toujours (User::hasAdminPermission()
 * court-circuite dessus) : aucune régression pour les routes déjà utilisées par
 * super_admin avant l'introduction du système de permissions granulaires.
 */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user() || ! $request->user()->hasAdminPermission($permission)) {
            abort(403, "Vous n'avez pas la permission nécessaire pour effectuer cette action.");
        }

        return $next($request);
    }
}
