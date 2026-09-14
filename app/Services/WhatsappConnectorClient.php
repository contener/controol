<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP vers le service indépendant whatsapp-connector/ (Node.js, hors de ce repo
 * Laravel côté exécution). Tant qu'aucun hébergement adapté n'héberge ce service,
 * services.whatsapp_connector.url reste vide et configure() renvoie false — les autres
 * méthodes ne sont alors jamais censées être appelées (voir WhatsAppAgentController).
 */
class WhatsappConnectorClient
{
    public function configure(): bool
    {
        return filled(config('services.whatsapp_connector.url'));
    }

    public function demarrer(int $agentId): array
    {
        return $this->appeler('post', "/sessions/{$agentId}/start");
    }

    public function statut(int $agentId): array
    {
        return $this->appeler('get', "/sessions/{$agentId}/status");
    }

    public function arreter(int $agentId): array
    {
        return $this->appeler('post', "/sessions/{$agentId}/stop");
    }

    /**
     * Le service Node peut être injoignable (pas encore déployé, en cours de redémarrage,
     * hébergement temporairement indisponible) — jamais laisser une exception réseau
     * remonter jusqu'à l'utilisateur : la page doit toujours pouvoir s'afficher.
     */
    private function appeler(string $methode, string $chemin): array
    {
        $url = rtrim((string) config('services.whatsapp_connector.url'), '/').$chemin;
        $secret = (string) config('services.whatsapp_connector.secret');

        try {
            $reponse = Http::withHeaders(['X-Connector-Secret' => $secret])
                ->timeout(10)
                ->{$methode}($url);

            if ($reponse->failed()) {
                Log::warning('whatsapp-connector: réponse en échec', ['url' => $url, 'status' => $reponse->status()]);

                return ['statut' => 'indisponible', 'qr' => null, 'numero' => null];
            }

            return $reponse->json() ?? ['statut' => 'indisponible', 'qr' => null, 'numero' => null];
        } catch (\Throwable $e) {
            Log::warning('whatsapp-connector: injoignable', ['url' => $url, 'message' => $e->getMessage()]);

            return ['statut' => 'indisponible', 'qr' => null, 'numero' => null];
        }
    }
}
