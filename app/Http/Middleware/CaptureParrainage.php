<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture ?ref=CODE sur la page d'inscription et le place en session -- même
 * principe que CaptureInvitationBoutique, mais l'attribution du parrain n'a lieu
 * qu'à l'inscription (un seul point de consommation, voir ParrainageService).
 */
class CaptureParrainage
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->routeIs('register') && $request->filled('ref')) {
            $existe = User::where('code_parrainage', $request->string('ref')->toString())->exists();

            if ($existe) {
                $request->session()->put('parrainage_code', $request->string('ref')->toString());
            }
        }

        return $next($request);
    }
}
