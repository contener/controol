<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique le limiteur "inscription" (FortifyServiceProvider) à la route
 * d'inscription web de Fortify (POST /register, "register.store"), que Fortify ne
 * permet pas de limiter par configuration -- audit sécurité V-06. L'API
 * (/api/register) utilise directement throttle:inscription.
 */
class LimiteInscriptions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->routeIs('register.store')) {
            return app(ThrottleRequests::class)->handle($request, $next, 'inscription');
        }

        return $next($request);
    }
}
