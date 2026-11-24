<?php

namespace App\Services;

use App\Models\EssaiUtilisateur;
use App\Models\Paiement;
use App\Models\PaiementAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementValidationService
{
    /**
     * Approuve un paiement en attente : active l'abonnement lié, calcule sa date
     * d'expiration selon la durée du plan, désactive l'ancien abonnement actif de
     * l'utilisateur, et journalise l'action. N'est jamais appelé en dehors d'une route
     * protégée par le middleware 'super_admin' — $admin n'est donc jamais l'utilisateur
     * lui-même qui valide son propre paiement (RULE 3/6) -- contrôlé par
     * AdminPaiementController (audit sécurité V-05).
     *
     * Le paiement est relu et verrouillé dans la transaction (audit sécurité V-08) :
     * deux validations simultanées (double clic, deux administrateurs) ne peuvent
     * jamais activer deux fois l'abonnement -- la seconde renvoie null.
     */
    public function approuver(Paiement $paiement, User $admin, Request $request): ?Paiement
    {
        return DB::transaction(function () use ($paiement, $admin, $request) {
            $paiement = Paiement::whereKey($paiement->getKey())->lockForUpdate()->first();

            if (! $paiement || ! $paiement->estEnAttente()) {
                return null;
            }

            $statutAvant = $paiement->statut;

            $paiement->update([
                'statut' => Paiement::STATUT_APPROUVE,
                'valide_par' => $admin->id,
                'valide_at' => now(),
                'motif_rejet' => null,
            ]);

            $this->activerAbonnement($paiement);

            $this->journaliser($paiement, $admin, 'approbation', $statutAvant, Paiement::STATUT_APPROUVE, null, $request);

            $paiement = $paiement->fresh(['abonnement.plan', 'user']);
            app(NotificationService::class)->paiementTraite($paiement);

            return $paiement;
        });
    }

    /**
     * Approbation automatique d'un paiement en ligne (CamerPay) -- appelée uniquement
     * par PaiementEnLigneService après vérification SERVEUR auprès du prestataire
     * (statut "completed", montant et référence identiques), jamais sur la seule
     * déclaration de l'application ou d'une notification. Mêmes effets que
     * approuver() (activerAbonnement(), notification), même verrou anti-double
     * activation ; journalisée sans administrateur (action "paiement_en_ligne").
     */
    public function confirmerEnLigne(Paiement $paiement, string $referenceTransaction): ?Paiement
    {
        return DB::transaction(function () use ($paiement, $referenceTransaction) {
            $paiement = Paiement::whereKey($paiement->getKey())->lockForUpdate()->first();

            if (! $paiement || ! $paiement->estEnAttente()) {
                return null;
            }

            $statutAvant = $paiement->statut;

            $paiement->update([
                'statut' => Paiement::STATUT_APPROUVE,
                'valide_par' => null,
                'valide_at' => now(),
                'motif_rejet' => null,
                'moyen_paiement' => 'camerpay',
                'reference_transaction' => $referenceTransaction,
            ]);

            $this->activerAbonnement($paiement);

            $this->journaliser($paiement, null, 'paiement_en_ligne', $statutAvant, Paiement::STATUT_APPROUVE, null, null);

            $paiement = $paiement->fresh(['abonnement.plan', 'user']);
            app(NotificationService::class)->paiementTraite($paiement);

            return $paiement;
        });
    }

    /**
     * Effets d'un paiement approuvé, communs à l'approbation par un administrateur
     * et au paiement en ligne vérifié : un seul exemplaire de cette règle financière.
     */
    private function activerAbonnement(Paiement $paiement): void
    {
        if ($paiement->abonnement_id) {
            $abonnement = $paiement->abonnement;
            $plan = $abonnement->plan;

            // Un seul abonnement actif à la fois : les autres passent "expire".
            $abonnement->user->abonnements()
                ->where('id', '!=', $abonnement->id)
                ->where('statut', 'actif')
                ->update(['statut' => 'expire', 'date_fin' => now()]);

            $abonnement->update([
                'statut' => 'actif',
                'date_debut' => now(),
                'date_fin' => now()->addDays($plan->duree_jours),
            ]);

            // Marque l'essai converti (arrete les rappels quotidiens) sans toucher
            // a la logique d'abonnement ci-dessus, deja correcte : l'essai a deja
            // ete bascule "expire" par la boucle "un seul abonnement actif" plus
            // haut, independamment de ce marqueur.
            EssaiUtilisateur::where('user_id', $abonnement->user_id)
                ->whereNull('converti_a')
                ->whereNull('annule_a')
                ->update(['converti_a' => now()]);
        }

        app(ParrainageService::class)->creerCommissionSiEligible($paiement);
    }

    public function rejeter(Paiement $paiement, User $admin, string $motif, Request $request): ?Paiement
    {
        return DB::transaction(function () use ($paiement, $admin, $motif, $request) {
            $paiement = Paiement::whereKey($paiement->getKey())->lockForUpdate()->first();

            if (! $paiement || ! $paiement->estEnAttente()) {
                return null;
            }

            $statutAvant = $paiement->statut;

            $paiement->update([
                'statut' => Paiement::STATUT_REJETE,
                'valide_par' => $admin->id,
                'valide_at' => now(),
                'motif_rejet' => $motif,
            ]);

            // Le plan associé reste inactif : on annule simplement la demande d'abonnement.
            if ($paiement->abonnement_id && $paiement->abonnement?->statut === 'en_attente') {
                $paiement->abonnement->update(['statut' => 'annule']);
            }

            $this->journaliser($paiement, $admin, 'rejet', $statutAvant, Paiement::STATUT_REJETE, $motif, $request);

            $paiement = $paiement->fresh(['abonnement.plan', 'user']);
            app(NotificationService::class)->paiementTraite($paiement);

            return $paiement;
        });
    }

    private function journaliser(Paiement $paiement, ?User $admin, string $action, string $statutAvant, string $statutApres, ?string $motif, ?Request $request): void
    {
        PaiementAudit::create([
            'paiement_id' => $paiement->id,
            'user_id' => $paiement->user_id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'statut_avant' => $statutAvant,
            'statut_apres' => $statutApres,
            'motif' => $motif,
            'ip_address' => $request?->ip(),
        ]);
    }
}
