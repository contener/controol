<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const LOCALES_DISPONIBLES = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale');

        if (in_array($locale, self::LOCALES_DISPONIBLES, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
