<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('abonnements:expirer')->daily();

// L'heure est relue en base a chaque evaluation du planning (ce fichier est charge a
// chaque appel artisan, donc a chaque execution de schedule:run) : un changement fait
// par le Super Admin prend effet le jour meme, sans redeploiement. Repli defensif sur
// 08:00 si la table n'existe pas encore (ex. avant la toute premiere migration) ou si
// la base est momentanement injoignable -- ce fichier se charge pour CHAQUE commande
// artisan, jamais uniquement schedule:run, donc une exception ici casserait tout.
Schedule::command('essais:notifier')->dailyAt((function () {
    try {
        $heure = \App\Models\ParametreEssai::actuel()->heure_notification;

        return substr((string) $heure, 0, 5);
    } catch (\Throwable $e) {
        return '08:00';
    }
})());
