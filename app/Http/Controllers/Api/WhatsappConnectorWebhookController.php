<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reçu uniquement par le service indépendant whatsapp-connector/ (Node.js), jamais par un
 * navigateur — authentifié par secret partagé (pas de session utilisateur), donc routé via
 * routes/api.php qui est stateless par défaut (pas de CSRF à contourner).
 */
class WhatsappConnectorWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secretAttendu = (string) config('services.whatsapp_connector.secret');
        $secretRecu = (string) $request->header('X-Connector-Secret', '');

        if ($secretAttendu === '' || ! hash_equals($secretAttendu, $secretRecu)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'agentId' => ['required', 'integer'],
            'statut' => ['required', 'string', 'in:deconnecte,connexion,qr,connecte'],
            'numero' => ['nullable', 'string'],
        ]);

        $agent = WhatsAppAgent::find($data['agentId']);

        if (! $agent) {
            return response()->json(['error' => 'agent_introuvable'], 404);
        }

        $agent->update([
            'whatsapp_statut' => $data['statut'],
            'whatsapp_numero' => $data['numero'] ?? null,
            'whatsapp_connecte_a' => $data['statut'] === 'connecte' ? ($agent->whatsapp_connecte_a ?? now()) : null,
        ]);

        return response()->json(['ok' => true]);
    }
}
