<?php

namespace App\Services;

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
     * lui-même qui valide son propre paiement (RULE 3/6).
     */
    public function approuver(Paiement $paiement, User $admin, Request $request): Paiement
    {
        return DB::transaction(function () use ($paiement, $admin, $request) {
            $statutAvant = $paiement->statut;

            $paiement->update([
                'statut' => Paiement::STATUT_APPROUVE,
                'valide_par' => $admin->id,
                'valide_at' => now(),
                'motif_rejet' => null,
            ]);

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
            }

            $this->journaliser($paiement, $admin, 'approbation', $statutAvant, Paiement::STATUT_APPROUVE, null, $request);

            return $paiement->fresh(['abonnement.plan', 'user']);
        });
    }

    public function rejeter(Paiement $paiement, User $admin, string $motif, Request $request): Paiement
    {
        return DB::transaction(function () use ($paiement, $admin, $motif, $request) {
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

            return $paiement->fresh(['abonnement.plan', 'user']);
        });
    }

    private function journaliser(Paiement $paiement, User $admin, string $action, string $statutAvant, string $statutApres, ?string $motif, Request $request): void
    {
        PaiementAudit::create([
            'paiement_id' => $paiement->id,
            'user_id' => $paiement->user_id,
            'admin_id' => $admin->id,
            'action' => $action,
            'statut_avant' => $statutAvant,
            'statut_apres' => $statutApres,
            'motif' => $motif,
            'ip_address' => $request->ip(),
        ]);
    }
}
