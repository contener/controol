<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        // Laravel garde par défaut le cookie "Se souvenir de moi" valide ~400 jours
        // (576000 minutes) -- ramené à 30 jours : reste confortable au quotidien sans
        // laisser une session traîner pendant plus d'un an sur un appareil partagé/perdu.
        Auth::guard('web')->setRememberDuration(60 * 24 * 30);
    }
}
