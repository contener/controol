<?php

namespace App\Services;

use App\Models\Paiement;
use App\Models\Plan;
use App\Models\TentativePaiement;
use App\Models\User;
use App\Services\CamerPay\CamerPayClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paiement en ligne des abonnements dans l'application (CamerPay, 2026-11-24).
 *
 * Même logique d'abonnement qu'avant : AbonnementService::demanderChangement() crée
 * (ou réutilise) l'abonnement et le Paiement "en attente" avec le montant calculé
 * côté serveur (prix promo d'essai compris) ; seul le moyen de régler change.
 *
 * Règles de sécurité :
 *  - la clé CamerPay ne quitte jamais le serveur (CamerPayClient) ;
 *  - le montant envoyé au prestataire est celui du Paiement en base, jamais une
 *    valeur venant de l'application ;
 *  - la référence envoyée (merchant_invoice_id) est aléatoire et non devinable ;
 *  - un paiement n'est approuvé QUE si le serveur a lui-même interrogé CamerPay et
 *    obtenu : statut "completed", même référence, même montant, même devise. Ni le
 *    retour de l'application, ni une notification seule ne suffisent ;
 *  - la notification doit porter une signature HMAC-SHA256 valide (comparaison en
 *    temps constant) ; elle ne fait que déclencher cette vérification ;
 *  - l'approbation passe par PaiementValidationService::confirmerEnLigne(), verrouillée :
 *    une notification rejouée ou deux vérifications simultanées n'activent jamais
 *    deux fois l'abonnement.
 */
class PaiementEnLigneService
{
    /** Délai minimal entre deux interrogations de CamerPay pour une même tentative. */
    private const INTERVALLE_VERIFICATION_SECONDES = 4;

    public function __construct(
        private readonly CamerPayClient $camerpay,
        private readonly AbonnementService $abonnements,
        private readonly PaiementValidationService $validation,
    ) {
    }

    public function disponible(): bool
    {
        return $this->camerpay->estConfigure();
    }

    /**
     * @return array{reference: string, pay_url: string, montant: float, devise: string}
     */
    public function demarrer(User $user, Plan $plan): array
    {
        if (! $this->disponible()) {
            throw new RuntimeException('Le paiement en ligne n\'est pas encore disponible.');
        }

        abort_if($plan->prix <= 0, 422, 'Ce plan ne nécessite aucun paiement.');

        $demande = $this->abonnements->demanderChangement($user, $plan);
        $paiement = Paiement::where('user_id', $user->id)->findOrFail($demande['paiement_id'] ?? 0);
        abort_unless($paiement->estEnAttente(), 409, 'Ce paiement a déjà été traité.');

        $reference = 'CTRL-'.Str::upper((string) Str::ulid()).'-'.Str::upper(Str::random(6));
        $montant = (int) round((float) $paiement->montant);

        $tentative = TentativePaiement::create([
            'paiement_id' => $paiement->id,
            'user_id' => $user->id,
            'prestataire' => 'camerpay',
            'reference_marchand' => $reference,
            'montant' => $montant,
            'devise' => $paiement->devise,
            'statut' => TentativePaiement::EN_COURS,
        ]);

        try {
            $resultat = $this->initierChezCamerPay($user, $paiement, $montant, $reference);
        } catch (RuntimeException $e) {
            $tentative->update(['statut' => TentativePaiement::ECHOUEE]);

            throw $e;
        }

        $tentative->update(['reference_prestataire' => $resultat['uuid'], 'statut_prestataire' => $resultat['statut']]);
        $paiement->update(['moyen_paiement' => 'camerpay']);

        return [
            'reference' => $reference,
            'pay_url' => $resultat['pay_url'],
            'montant' => (float) $montant,
            'devise' => $paiement->devise,
        ];
    }

    /** @return array{uuid: string, pay_url: string, statut: ?string} */
    private function initierChezCamerPay(User $user, Paiement $paiement, int $montant, string $reference): array
    {
        return $this->camerpay->initier(array_filter([
            'amount' => $montant,
            'currency' => $paiement->devise,
            'merchant_invoice_id' => $reference,
            'idempotency_key' => $reference,
            'merchant_callback_url' => route('api.camerpay.notification'),
            'merchant_return_url' => route('paiement.retour', ['ref' => $reference]),
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->telephone,
            'source' => 'mobile',
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Interroge CamerPay pour cette tentative et approuve le paiement si (et seulement
     * si) tout concorde. Sans effet si la tentative est déjà terminée.
     */
    public function verifier(TentativePaiement $tentative, bool $forcer = false): TentativePaiement
    {
        if ($tentative->estTerminee() || ! $tentative->reference_prestataire) {
            return $tentative;
        }

        if (! $forcer && $tentative->verifiee_le && $tentative->verifiee_le->diffInSeconds(now()) < self::INTERVALLE_VERIFICATION_SECONDES) {
            return $tentative;
        }

        $tentative->update(['verifiee_le' => now()]);

        try {
            $etat = $this->camerpay->statut($tentative->reference_prestataire);
        } catch (RuntimeException) {
            return $tentative;
        }

        $tentative->update(['statut_prestataire' => Str::limit($etat['statut'], 30, '')]);

        if ($etat['statut'] === 'completed') {
            if (! $this->concorde($tentative, $etat)) {
                Log::warning('CamerPay : paiement "completed" non concordant, non approuvé.', ['tentative' => $tentative->id]);

                return $tentative;
            }

            return $this->confirmer($tentative);
        }

        if (in_array($etat['statut'], ['failed', 'cancelled', 'canceled', 'expired', 'rejected', 'refunded'], true)) {
            $tentative->update(['statut' => TentativePaiement::ECHOUEE]);
        }

        return $tentative->refresh();
    }

    /**
     * Notification CamerPay (champs uuid, invoice_id, status, amount, signature).
     * Renvoie false si la signature est invalide ou la transaction inconnue.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function traiterNotification(array $donnees, string $signature): bool
    {
        $uuid = (string) ($donnees['uuid'] ?? '');
        $reference = (string) ($donnees['invoice_id'] ?? $donnees['merchant_invoice_id'] ?? '');
        $statut = (string) ($donnees['status'] ?? '');
        $montant = (string) ($donnees['amount'] ?? '');

        if ($uuid === '' || $reference === '' || ! $this->camerpay->signatureValide($uuid, $reference, $statut, $montant, $signature)) {
            return false;
        }

        $tentative = TentativePaiement::where('reference_prestataire', $uuid)->where('reference_marchand', $reference)->first();
        if (! $tentative) {
            return false;
        }

        $tentative->update(['empreinte_notification' => hash('sha256', $uuid.'|'.$reference.'|'.$statut.'|'.$montant)]);

        // La notification ne prouve rien à elle seule : vérification auprès de CamerPay.
        $this->verifier($tentative, forcer: true);

        return true;
    }

    /** @param  array{statut: string, montant: ?float, reference_marchand: ?string, devise: ?string}  $etat */
    private function concorde(TentativePaiement $tentative, array $etat): bool
    {
        $paiement = $tentative->paiement;

        // Montant OBLIGATOIRE dans la réponse de statut : sans lui, aucune approbation.
        if ($etat['montant'] === null || abs($etat['montant'] - (float) $tentative->montant) > 0.01) {
            return false;
        }

        if (abs((float) $tentative->montant - round((float) $paiement->montant)) > 0.01) {
            return false;
        }

        if ($etat['reference_marchand'] !== null && ! hash_equals($tentative->reference_marchand, $etat['reference_marchand'])) {
            return false;
        }

        return $etat['devise'] === null || $etat['devise'] === strtoupper($tentative->devise);
    }

    private function confirmer(TentativePaiement $tentative): TentativePaiement
    {
        DB::transaction(function () use ($tentative) {
            $verrouillee = TentativePaiement::whereKey($tentative->id)->lockForUpdate()->first();
            if (! $verrouillee || $verrouillee->estTerminee()) {
                return;
            }

            $verrouillee->update(['statut' => TentativePaiement::CONFIRMEE, 'confirmee_le' => now()]);
            $this->validation->confirmerEnLigne($verrouillee->paiement, (string) $verrouillee->reference_prestataire);
        });

        return $tentative->refresh();
    }
}
