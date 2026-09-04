<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'boutique.selected' => \App\Http\Middleware\EnsureBoutiqueSelected::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'admin.access' => \App\Http\Middleware\EnsureAdminAccess::class,
            'admin.permission' => \App\Http\Middleware\EnsureAdminPermission::class,
            'account.active' => \App\Http\Middleware\EnsureAccountActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un 403 (ex: EnsureSuperAdmin) sur une requête "page" (pas un appel JSON/API
        // explicite) rend la page Inertia "Accès refusé" plutôt que la page d'erreur
        // brute de Laravel. Un appel JSON direct (ex: sonde manuelle de l'API) reçoit
        // toujours un 403 propre sans page HTML ni fuite de données.
        $exceptions->respond(function ($response, $throwable, $request) {
            if ($response->getStatusCode() === 403 && ! $request->expectsJson()) {
                return Inertia::render('Errors/AccesRefuse', [
                    'message' => $throwable->getMessage() ?: "Vous n'avez pas l'autorisation d'accéder à cette page.",
                ])->toResponse($request)->setStatusCode(403);
            }

            return $response;
        });
    })->create();
