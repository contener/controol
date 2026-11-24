<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaiementEnLigneService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Notification serveur→serveur de CamerPay (merchant_callback_url). Signature
 * HMAC-SHA256 obligatoire (champ "signature" ou en-tête X-CamerPay-Signature) ; une
 * notification valide déclenche seulement une vérification auprès de CamerPay, elle
 * n'approuve jamais un paiement par elle-même -- voir PaiementEnLigneService.
 */
class CamerPayNotificationController extends Controller
{
    public function __invoke(Request $request, PaiementEnLigneService $paiements): Response
    {
        $donnees = $request->only(['uuid', 'invoice_id', 'merchant_invoice_id', 'status', 'amount']);
        $signature = (string) ($request->input('signature') ?: $request->header('X-CamerPay-Signature', ''));

        if (! $paiements->traiterNotification($donnees, $signature)) {
            Log::warning('CamerPay : notification refusée (signature invalide ou transaction inconnue).', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        return response('OK', 200);
    }
}
