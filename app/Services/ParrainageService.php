<?php

namespace App\Services;

use App\Models\AdminAudit;
use App\Models\CommissionParrainage;
use App\Models\NotificationUtilisateur;
use App\Models\Paiement;
use App\Models\ReglementParrainage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * Cœur métier du parrainage : génération du code, attribution du parrain à
 * l'inscription, création de commission à la validation d'un paiement, et calcul du
 * solde (modèle "grand livre" -- jamais de statut "payée" sur une ligne de commission,
 * le montant déjà réglé se déduit de reglements_parrainage, cf. plan).
 */
class ParrainageService
{
    public const TAUX_COMMISSION = 5.00;

    public function assurerCodeParrainage(User $user): string
    {
        if ($user->code_parrainage) {
            return $user->code_parrainage;
        }

        do {
            $code = Str::upper(Str::random(8));
        } while (User::where('code_parrainage', $code)->exists());

        $user->forceFill(['code_parrainage' => $code])->save();

        return $code;
    }

    /**
     * Appelée depuis CreateNewUser::create(), dans le même tap() que
     * InvitationBoutiqueService::apresInscription() -- attribution du parrain à
     * l'inscription uniquement, jamais un deuxième point de consommation (contrairement
     * au suivi de boutique).
     */
    public function apresInscription(User $nouvelUtilisateur): void
    {
        $code = Session::get('parrainage_code');

        if (! $code) {
            return;
        }

        Session::forget('parrainage_code');

        $parrain = User::where('code_parrainage', $code)->first();

        if (! $parrain || $parrain->id === $nouvelUtilisateur->id) {
            return;
        }

        if ($nouvelUtilisateur->parrain_id !== null) {
            return;
        }

        $nouvelUtilisateur->forceFill(['parrain_id' => $parrain->id])->save();
    }

    /**
     * Appelée depuis PaiementValidationService::approuver(), déjà dans une transaction
     * DB -- aucune commission ne peut donc exister sans validation réelle du paiement.
     */
    public function creerCommissionSiEligible(Paiement $paiement): void
    {
        $filleul = $paiement->user;

        if (! $filleul || ! $filleul->parrain_id) {
            return;
        }

        if (CommissionParrainage::where('paiement_id', $paiement->id)->exists()) {
            return;
        }

        $montantCommission = round((float) $paiement->montant * self::TAUX_COMMISSION / 100, 2);

        $commission = CommissionParrainage::create([
            'parrain_id' => $filleul->parrain_id,
            'filleul_id' => $filleul->id,
            'paiement_id' => $paiement->id,
            'montant_eligible' => $paiement->montant,
            'taux' => self::TAUX_COMMISSION,
            'montant_commission' => $montantCommission,
            'statut' => 'disponible',
        ]);

        $this->notifierParrain($commission);
    }

    private function notifierParrain(CommissionParrainage $commission): void
    {
        try {
            NotificationUtilisateur::create([
                'user_id' => $commission->parrain_id,
                'type' => 'commission_parrainage',
                'titre' => 'Nouvelle commission de parrainage',
                'message' => "Vous avez gagné {$this->formaterMontant($commission->montant_commission)} FCFA de commission grâce à un paiement de votre filleul.",
                'lien' => route('compte.parrainage'),
                'est_promotionnelle' => false,
            ]);
        } catch (\Throwable $e) {
            // Best-effort : ne doit jamais faire échouer l'approbation du paiement.
        }
    }

    public function statistiques(User $parrain): array
    {
        $filleulIds = $parrain->filleuls()->pluck('id');

        $gainsGeneres = (float) $parrain->commissionsGagnees()->actives()->sum('montant_commission');
        $gainsPayes = (float) $parrain->reglementsParrainage()->sum('montant');

        return [
            'comptes_crees' => $filleulIds->count(),
            'paiements_commences' => Paiement::whereIn('user_id', $filleulIds)->distinct('user_id')->count('user_id'),
            'paiements_valides' => Paiement::whereIn('user_id', $filleulIds)->where('statut', Paiement::STATUT_APPROUVE)->distinct('user_id')->count('user_id'),
            'gains_generes' => $gainsGeneres,
            'gains_payes' => $gainsPayes,
            'solde_disponible' => round($gainsGeneres - $gainsPayes, 2),
        ];
    }

    public function soldeDisponible(User $parrain): float
    {
        $gainsGeneres = (float) $parrain->commissionsGagnees()->actives()->sum('montant_commission');
        $gainsPayes = (float) $parrain->reglementsParrainage()->sum('montant');

        return round($gainsGeneres - $gainsPayes, 2);
    }

    public function enregistrerReglement(User $parrain, float $montant, array $details, User $admin, Request $request): ReglementParrainage
    {
        abort_if($montant <= 0, 422, 'Le montant du règlement doit être positif.');
        abort_if($montant > $this->soldeDisponible($parrain), 422, 'Le montant dépasse le solde disponible du parrain.');

        $reglement = ReglementParrainage::create([
            'parrain_id' => $parrain->id,
            'montant' => $montant,
            'methode_paiement' => $details['methode_paiement'] ?? null,
            'reference_transaction' => $details['reference_transaction'] ?? null,
            'notes' => $details['notes'] ?? null,
            'traite_par' => $admin->id,
        ]);

        AdminAudit::create([
            'admin_id' => $admin->id,
            'action' => 'parrainage_reglement',
            'resource' => 'reglement_parrainage',
            'resource_id' => $reglement->id,
            'ancienne_valeur' => null,
            'nouvelle_valeur' => ['parrain_id' => $parrain->id, 'montant' => $montant],
            'ip_address' => $request->ip(),
        ]);

        return $reglement;
    }

    public function annulerCommission(CommissionParrainage $commission, User $admin, string $motif, Request $request): void
    {
        $ancienStatut = $commission->statut;

        $commission->update([
            'statut' => 'annulee',
            'annule_par' => $admin->id,
            'annule_at' => now(),
            'motif_annulation' => $motif,
        ]);

        AdminAudit::create([
            'admin_id' => $admin->id,
            'action' => 'parrainage_commission_annulee',
            'resource' => 'commission_parrainage',
            'resource_id' => $commission->id,
            'ancienne_valeur' => ['statut' => $ancienStatut],
            'nouvelle_valeur' => ['statut' => 'annulee', 'motif' => $motif],
            'ip_address' => $request->ip(),
        ]);
    }

    private function formaterMontant(float $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }
}
