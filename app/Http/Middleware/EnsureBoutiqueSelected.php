<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBoutiqueSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->current_boutique_id) {
            return redirect()->route('boutiques.create')
                ->with('flash_error', "Créez d'abord votre boutique pour continuer.");
        }

        return $next($request);
    }
}
