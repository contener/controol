<?php

namespace App\Services\CamerPay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Appels serveur→CamerPay (seul endroit qui connaît la clé API, lue dans
 * config('services.camerpay')). Format repris du SDK PHP officiel camerpay/php-sdk :
 * Bearer token, POST /payment/initiate, GET /payment/{uuid}/status. Les réponses sont
 * lues de façon tolérante (clés à plat ou sous "transaction"), car le format exact
 * n'est pas documenté publiquement ; seules les valeurs utiles sont renvoyées.
 *
 * Jamais de journalisation de la clé, des en-têtes ou du corps complet des réponses.
 */
class CamerPayClient
{
    public function estConfigure(): bool
    {
        return filled(config('services.camerpay.api_key')) && filled(config('services.camerpay.webhook_secret'));
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{uuid: string, pay_url: string, statut: ?string}
     */
    public function initier(array $donnees): array
    {
        $corps = $this->appel('post', '/payment/initiate', $donnees);

        $transaction = is_array($corps['transaction'] ?? null) ? $corps['transaction'] : [];
        $uuid = (string) ($transaction['uuid'] ?? $corps['transaction_uuid'] ?? $corps['uuid'] ?? '');
        $url = (string) ($corps['pay_url'] ?? $corps['payment_url'] ?? $corps['redirect_url'] ?? $transaction['pay_url'] ?? '');

        if ($uuid === '' || ! $this->urlDePaiementSure($url)) {
            Log::warning('CamerPay : réponse d\'initiation inexploitable.', ['cles' => array_keys($corps)]);

            throw new RuntimeException('Le service de paiement a renvoyé une réponse inattendue.');
        }

        return ['uuid' => $uuid, 'pay_url' => $url, 'statut' => $transaction['status'] ?? $corps['status'] ?? null];
    }

    /**
     * @return array{statut: string, montant: ?float, reference_marchand: ?string, devise: ?string}
     */
    public function statut(string $uuid): array
    {
        $corps = $this->appel('get', '/payment/'.rawurlencode($uuid).'/status');
        $t = is_array($corps['transaction'] ?? null) ? $corps['transaction'] : $corps;

        $montant = $t['amount'] ?? $corps['amount'] ?? null;
        $reference = $t['merchant_invoice_id'] ?? $t['invoice_id'] ?? $corps['merchant_invoice_id'] ?? $corps['invoice_id'] ?? null;

        return [
            'statut' => strtolower((string) ($t['status'] ?? $corps['status'] ?? '')),
            'montant' => is_numeric($montant) ? (float) $montant : null,
            'reference_marchand' => $reference !== null ? (string) $reference : null,
            'devise' => isset($t['currency']) ? strtoupper((string) $t['currency']) : null,
        ];
    }

    /**
     * Signature des notifications (même calcul que Webhooks\Verifier du SDK PHP) :
     * HMAC-SHA256 hexadécimal de "uuid|invoice_id|status|amount" avec le secret de
     * notification, comparé en temps constant.
     */
    public function signatureValide(string $uuid, string $referenceMarchand, string $statut, string $montant, string $signature): bool
    {
        $secret = (string) config('services.camerpay.webhook_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        $attendue = hash_hmac('sha256', $uuid.'|'.$referenceMarchand.'|'.$statut.'|'.$montant, $secret);

        return hash_equals($attendue, strtolower($signature));
    }

    /**
     * La page de paiement ouverte dans l'application doit être en HTTPS et chez
     * CamerPay (même domaine que l'API configurée) -- jamais une adresse arbitraire.
     */
    private function urlDePaiementSure(string $url): bool
    {
        $hote = parse_url($url, PHP_URL_HOST);
        $hoteApi = parse_url((string) config('services.camerpay.base_url'), PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($hote) || ! is_string($hoteApi)) {
            return false;
        }

        $domaine = implode('.', array_slice(explode('.', $hoteApi), -2));

        return $hote === $domaine || str_ends_with($hote, '.'.$domaine);
    }

    /** @return array<string, mixed> */
    private function appel(string $methode, string $chemin, array $donnees = []): array
    {
        if (! $this->estConfigure()) {
            throw new RuntimeException('Le paiement en ligne n\'est pas encore configuré.');
        }

        $url = rtrim((string) config('services.camerpay.base_url'), '/').'/'.ltrim($chemin, '/');

        try {
            $reponse = Http::withToken((string) config('services.camerpay.api_key'))
                ->acceptJson()
                ->asJson()
                ->withUserAgent('Controool/1.0')
                ->timeout(25)
                ->{$methode}($url, $methode === 'get' ? null : $donnees);
        } catch (ConnectionException) {
            throw new RuntimeException('Le service de paiement est injoignable. Réessayez dans un instant.');
        }

        if (! $reponse->successful()) {
            Log::warning('CamerPay : appel refusé.', ['chemin' => $chemin, 'statut_http' => $reponse->status()]);

            throw new RuntimeException(match ($reponse->status()) {
                402 => 'Le service de paiement a refusé l\'opération (plafond atteint).',
                422 => 'Le service de paiement a refusé les informations envoyées.',
                default => 'Le service de paiement n\'a pas pu traiter la demande. Réessayez dans un instant.',
            });
        }

        $corps = $reponse->json();

        return is_array($corps) ? $corps : [];
    }
}
