<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de sécurité pour un compte désactivé (est_actif=false) en cours de session —
 * FortifyServiceProvider::authenticateUsing() bloque déjà une NOUVELLE connexion, mais
 * un utilisateur déjà connecté au moment où le Super Admin le désactive doit aussi être
 * coupé immédiatement, pas seulement à sa prochaine tentative de connexion. Appliqué à
 * tout le groupe de routes authentifiées (routes/web.php).
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->est_actif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('flash_error', 'Votre compte a été désactivé. Contactez le support si vous pensez qu\'il s\'agit d\'une erreur.');
        }

        return $next($request);
    }
}
