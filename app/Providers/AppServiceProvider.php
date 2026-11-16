<?php

namespace App\Providers;

use App\Models\Conversation;
use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
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

        // Messagerie unifiée de l'application mobile : une conversation reçue par
        // n'importe quelle boutique gérée par l'utilisateur s'ouvre sans changer de
        // boutique courante. Résolue hors du filtre BelongsToBoutique ; l'accès reste
        // contrôlé par ConversationPolicy (permission Messagerie sur SA boutique).
        Route::bind('conversationGeree', fn ($valeur) => Conversation::withoutGlobalScopes()->findOrFail((int) $valeur));

        // Laravel garde par défaut le cookie "Se souvenir de moi" valide ~400 jours
        // (576000 minutes) -- ramené à 30 jours : reste confortable au quotidien sans
        // laisser une session traîner pendant plus d'un an sur un appareil partagé/perdu.
        Auth::guard('web')->setRememberDuration(60 * 24 * 30);
    }
}
