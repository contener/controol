<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Équivalent API d'EnsureAccountActive (audit sécurité V-01, 2026-11-14) : un compte
 * désactivé par un administrateur ne doit plus pouvoir utiliser un jeton mobile
 * obtenu avant sa désactivation. Le jeton courant est révoqué et la requête
 * refusée (401), ce qui renvoie l'application à l'écran de connexion -- où
 * AuthController::login() refuse déjà tout compte inactif.
 */
class EnsureApiAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->est_actif) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => "Ce compte a été désactivé. Contactez le support si vous pensez qu'il s'agit d'une erreur.",
            ], 401);
        }

        return $next($request);
    }
}
