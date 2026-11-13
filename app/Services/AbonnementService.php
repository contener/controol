<?php

namespace App\Services;

use App\Enums\EssaiStatut;
use App\Models\EssaiUtilisateur;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\User;

/**
 * Demande de changement de plan -- extraite d'AbonnementController (web) pour être
 * partagée telle quelle avec Api\AbonnementController (app mobile) : une règle
 * financière (prix promo d'essai, lien de paiement, abonnement "en_attente" tant
 * qu'un Super Admin n'a pas validé) ne doit jamais exister en deux exemplaires
 * susceptibles de diverger.
 */
class AbonnementService
{
    /**
     * @return array{gratuit: bool, montant: float, devise: string, lien_paiement: ?string, message: string}
     */
    public function demanderChangement(User $user, Plan $plan): array
    {
        if ($plan->prix <= 0) {
            $user->abonnements()->create([
                'plan_id' => $plan->id,
                'statut' => 'actif',
                'date_debut' => now(),
            ]);

            return [
                'gratuit' => true,
                'montant' => 0.0,
                'devise' => $plan->devise,
                'lien_paiement' => null,
                'message' => 'Vous êtes maintenant sur le plan Gratuit.',
            ];
        }

        $abonnement = $user->abonnements()->create([
            'plan_id' => $plan->id,
            'statut' => 'en_attente',
            'date_debut' => now(),
        ]);

        // Prix promotionnel contrôlé côté serveur : jamais une valeur envoyée par le
        // client. On ne fait confiance qu'à un essai réellement en cours en base.
        $essai = EssaiUtilisateur::where('user_id', $user->id)->latest('id')->first();
        $enPromo = $plan->code === 'basique' && $essai?->statut() === EssaiStatut::EnCours;
        $montant = $enPromo ? $essai->prix_promo : $plan->prix;

        Paiement::create([
            'user_id' => $user->id,
            'abonnement_id' => $abonnement->id,
            'montant' => $montant,
            'devise' => $plan->devise,
            'moyen_paiement' => 'manuel',
            'statut' => 'en_attente',
        ]);

        // Le fournisseur de paiement hébergé a un montant fixe par lien : un essai en
        // cours (prix promo) et un abonnement classique (prix normal) ne peuvent donc
        // jamais partager le même lien pour le plan Basique. Repli sur lien_paiement si
        // aucun lien promo n'est configuré.
        //
        // L'abonnement reste "en_attente" tant qu'un Super Admin n'a pas vérifié et
        // approuvé le paiement (PaiementValidationService::approuver) — ce lien externe
        // ne fait qu'orienter l'utilisateur vers la page de paiement hébergée, il n'active
        // jamais l'abonnement lui-même (RULE 5).
        $lienPaiement = ($enPromo && $plan->lien_paiement_promo) ? $plan->lien_paiement_promo : $plan->lien_paiement;

        return [
            'gratuit' => false,
            'montant' => (float) $montant,
            'devise' => $plan->devise,
            'lien_paiement' => $lienPaiement ?: null,
            'message' => "Demande envoyée pour le plan {$plan->nom}. Votre abonnement sera activé dès confirmation du paiement ({$montant} {$plan->devise}).",
        ];
    }
}
