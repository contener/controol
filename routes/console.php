<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('abonnements:expirer')->daily();

// L'heure ET le fuseau sont relus en base a chaque evaluation du planning (ce fichier
// est charge a chaque appel artisan, donc a chaque execution de schedule:run) : un
// changement fait par le Super Admin prend effet le jour meme, sans redeploiement.
// Repli defensif sur 08:00/Africa/Douala si la table n'existe pas encore (ex. avant la
// toute premiere migration) ou si la base est momentanement injoignable -- ce fichier
// se charge pour CHAQUE commande artisan, jamais uniquement schedule:run, donc une
// exception ici casserait tout.
//
// IMPORTANT : sans ->timezone(), dailyAt() interprete l'heure dans APP_TIMEZONE (UTC
// en production) -- jamais dans le fuseau choisi par l'admin. Un admin au Cameroun
// (Africa/Douala, UTC+1) configurant "08:00" s'attend a 08:00 heure locale, pas 08:00
// UTC (= 09:00 locale, un decalage silencieux d'une heure sans ce ->timezone()).
[$heureNotification, $fuseauNotification] = (function () {
    try {
        $parametres = \App\Models\ParametreEssai::actuel();

        return [substr((string) $parametres->heure_notification, 0, 5), $parametres->fuseau];
    } catch (\Throwable $e) {
        return ['08:00', 'Africa/Douala'];
    }
})();

Schedule::command('essais:notifier')->dailyAt($heureNotification)->timezone($fuseauNotification);
