<?php

namespace App\Console\Commands;

use App\Enums\EssaiStatut;
use App\Models\EssaiUtilisateur;
use App\Models\ModeleNotificationEssai;
use App\Models\NotificationUtilisateur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotente : dernier_jour_notifie empeche toute relance (echec partiel, double
 * declenchement du planificateur...) de creer deux notifications pour le meme jour.
 * S'arrete d'elle-meme des qu'un essai est converti ou expire, sans logique separee
 * a maintenir (statut() est la seule source de verite, deja utilisee partout ailleurs).
 */
class NotifierEssaisEnCours extends Command
{
    protected $signature = 'essais:notifier';

    protected $description = "Envoie le rappel quotidien aux utilisateurs dont l'essai Basique est en cours";

    public function handle(): int
    {
        $modeles = ModeleNotificationEssai::where('actif', true)->get()->keyBy('jour');
        $traites = 0;

        EssaiUtilisateur::actifs()
            ->with(['user', 'plan'])
            ->chunkById(50, function ($essais) use ($modeles, &$traites) {
                foreach ($essais as $essai) {
                    if ($essai->statut() !== EssaiStatut::EnCours) {
                        continue;
                    }

                    $jour = $essai->jourActuel();

                    if ($essai->dernier_jour_notifie === $jour) {
                        continue;
                    }

                    $modele = $modeles->get($jour);

                    if (! $modele) {
                        continue;
                    }

                    DB::transaction(function () use ($essai, $modele, $jour) {
                        NotificationUtilisateur::create([
                            'user_id' => $essai->user_id,
                            'type' => 'essai_rappel',
                            'titre' => $modele->titre,
                            'message' => $this->interpoler($modele->message, $essai),
                            'est_promotionnelle' => true,
                            'created_at' => now(),
                        ]);

                        $essai->update(['dernier_jour_notifie' => $jour]);
                    });

                    $traites++;
                }
            });

        $this->info("{$traites} rappel(s) d'essai envoye(s).");

        return self::SUCCESS;
    }

    private function interpoler(string $message, EssaiUtilisateur $essai): string
    {
        return strtr($message, [
            '{{nom_utilisateur}}' => $essai->user->name,
            '{{jours_restants}}' => (string) $essai->joursRestants(),
            '{{date_fin}}' => $essai->date_fin->format('d/m/Y'),
            '{{prix_normal}}' => number_format((float) $essai->plan->prix, 0, ',', ' '),
            '{{prix_promotionnel}}' => number_format((float) $essai->prix_promo, 0, ',', ' '),
        ]);
    }
}
