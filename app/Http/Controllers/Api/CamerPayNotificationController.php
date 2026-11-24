<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaiementEnLigneService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Notification serveur→serveur de CamerPay (merchant_callback_url). Signature
 * HMAC-SHA256 obligatoire (champ "signature" ou en-tête X-CamerPay-Signature) : sinon
 * 401. Une notification valide déclenche seulement une vérification auprès de
 * CamerPay, elle n'approuve jamais un paiement par elle-même ; signée mais pour une
 * transaction inconnue (testeur de webhook CamerPay, uuid TEST-...) : 200 "OK" sans
 * aucun effet -- voir PaiementEnLigneService::traiterNotification().
 */
class CamerPayNotificationController extends Controller
{
    public function __invoke(Request $request, PaiementEnLigneService $paiements): Response
    {
        $donnees = $request->only(['uuid', 'invoice_id', 'merchant_invoice_id', 'status', 'amount']);
        $signature = (string) ($request->input('signature') ?: $request->header('X-CamerPay-Signature', ''));

        $resultat = $paiements->traiterNotification($donnees, $signature);

        if ($resultat === PaiementEnLigneService::NOTIFICATION_SIGNATURE_INVALIDE) {
            Log::warning('CamerPay : notification refusée (signature invalide).', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        if ($resultat === PaiementEnLigneService::NOTIFICATION_IGNOREE) {
            Log::info('CamerPay : notification signée pour une transaction inconnue, ignorée sans effet.', [
                'test' => $request->input('test') === '1' || $request->header('X-CamerPay-Test') === '1',
            ]);
        }

        return response('OK', 200);
    }
}
