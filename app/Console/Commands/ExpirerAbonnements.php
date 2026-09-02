<?php

namespace App\Console\Commands;

use App\Models\Abonnement;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * L'éligibilité (Marketplace, limites de plan) est déjà calculée en temps réel via
 * Abonnement::scopeActuellementActif() et ne dépend donc pas de cette commande pour
 * être correcte. Cette commande ne fait que "nettoyer" la donnée : elle fait passer en
 * statut 'expire' les abonnements payants dont la date_fin est dépassée, et fait
 * revenir l'utilisateur sur le plan Gratuit — pour que l'administration et l'historique
 * reflètent l'état réel, et que l'utilisateur retrouve les limites du plan Gratuit
 * plutôt qu'un état "sans aucun abonnement".
 */
class ExpirerAbonnements extends Command
{
    protected $signature = 'abonnements:expirer';

    protected $description = "Expire les abonnements payants dont la date de fin est dépassée et fait revenir l'utilisateur au plan Gratuit";

    public function handle(): int
    {
        $planGratuit = Plan::where('code', 'gratuit')->first();
        $traites = 0;

        Abonnement::where('statut', 'actif')
            ->whereNotNull('date_fin')
            ->where('date_fin', '<', now())
            ->with('user')
            ->chunkById(50, function ($abonnements) use ($planGratuit, &$traites) {
                foreach ($abonnements as $abonnement) {
                    DB::transaction(function () use ($abonnement, $planGratuit) {
                        $abonnement->update(['statut' => 'expire']);

                        $aDejaUnAbonnementActif = $abonnement->user->abonnements()
                            ->where('id', '!=', $abonnement->id)
                            ->actuellementActif()
                            ->exists();

                        if ($planGratuit && ! $aDejaUnAbonnementActif) {
                            $abonnement->user->abonnements()->create([
                                'plan_id' => $planGratuit->id,
                                'statut' => 'actif',
                                'date_debut' => now(),
                                'date_fin' => null,
                            ]);
                        }
                    });

                    $traites++;
                }
            });

        $this->info("{$traites} abonnement(s) expiré(s) traité(s).");

        return self::SUCCESS;
    }
}
