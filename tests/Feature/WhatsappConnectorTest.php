<?php

namespace Tests\Feature;

use App\Models\WhatsAppAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\CreatesBoutique;
use Tests\TestCase;

class WhatsappConnectorTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function configurerConnecteur(): void
    {
        Config::set('services.whatsapp_connector.url', 'http://connecteur.test');
        Config::set('services.whatsapp_connector.secret', 'test-secret');
    }

    /**
     * L'agent n'existe qu'après un premier accès (auto-provisionné par
     * WhatsAppAgentController::agentCourant(), cf. WhatsAppAgentTest.php) — jamais
     * juste après creerUtilisateurAvecBoutique().
     */
    private function agentDe($user): WhatsAppAgent
    {
        $this->actingAs($user);

        return WhatsAppAgent::firstOrCreate();
    }

    public function test_connecter_returns_indisponible_gracefully_when_connector_not_configured(): void
    {
        Config::set('services.whatsapp_connector.url', null);
        $user = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($user)->postJson(route('whatsapp-agent.connecter'));

        $response->assertOk()->assertJson(['statut' => 'indisponible']);
    }

    public function test_connecter_starts_a_session_and_syncs_qr_status(): void
    {
        $this->configurerConnecteur();
        Http::fake([
            'connecteur.test/*' => Http::response(['statut' => 'qr', 'qr' => 'data:image/png;base64,xyz', 'numero' => null]),
        ]);

        $user = $this->creerUtilisateurAvecBoutique();
        $agent = $this->agentDe($user);

        $response = $this->actingAs($user)->postJson(route('whatsapp-agent.connecter'));

        $response->assertOk()->assertJson(['statut' => 'qr', 'qr' => 'data:image/png;base64,xyz']);
        $this->assertSame('qr', $agent->fresh()->whatsapp_statut);

        Http::assertSent(fn ($request) => $request->url() === "http://connecteur.test/sessions/{$agent->id}/start"
            && $request->header('X-Connector-Secret')[0] === 'test-secret');
    }

    public function test_statut_connexion_syncs_connected_state_and_phone_number(): void
    {
        $this->configurerConnecteur();
        $user = $this->creerUtilisateurAvecBoutique();
        $agent = $this->agentDe($user);

        Http::fake([
            'connecteur.test/*' => Http::response(['statut' => 'connecte', 'qr' => null, 'numero' => '237690000000']),
        ]);

        $response = $this->actingAs($user)->getJson(route('whatsapp-agent.connexion.statut'));

        $response->assertOk()->assertJson(['statut' => 'connecte', 'numero' => '237690000000']);
        $agent->refresh();
        $this->assertSame('connecte', $agent->whatsapp_statut);
        $this->assertSame('237690000000', $agent->whatsapp_numero);
        $this->assertNotNull($agent->whatsapp_connecte_a);
    }

    public function test_deconnecter_resets_local_connection_state_even_if_connector_call_fails(): void
    {
        $this->configurerConnecteur();
        Http::fake([
            'connecteur.test/*' => Http::response([], 500),
        ]);

        $user = $this->creerUtilisateurAvecBoutique();
        $agent = $this->agentDe($user);
        $agent->update(['whatsapp_statut' => 'connecte', 'whatsapp_numero' => '237690000000', 'whatsapp_connecte_a' => now()]);

        $response = $this->actingAs($user)->postJson(route('whatsapp-agent.deconnecter'));

        $response->assertOk()->assertJson(['statut' => 'deconnecte']);
        $agent->refresh();
        $this->assertSame('deconnecte', $agent->whatsapp_statut);
        $this->assertNull($agent->whatsapp_numero);
    }

    public function test_webhook_updates_agent_when_secret_is_valid(): void
    {
        $this->configurerConnecteur();
        $user = $this->creerUtilisateurAvecBoutique();
        $agent = $this->agentDe($user);

        $response = $this->postJson('/api/webhooks/whatsapp-connector', [
            'agentId' => $agent->id,
            'statut' => 'connecte',
            'numero' => '237690000000',
        ], ['X-Connector-Secret' => 'test-secret']);

        $response->assertOk();
        $agent->refresh();
        $this->assertSame('connecte', $agent->whatsapp_statut);
        $this->assertSame('237690000000', $agent->whatsapp_numero);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $this->configurerConnecteur();
        $user = $this->creerUtilisateurAvecBoutique();
        $agent = $this->agentDe($user);

        $response = $this->postJson('/api/webhooks/whatsapp-connector', [
            'agentId' => $agent->id,
            'statut' => 'connecte',
        ], ['X-Connector-Secret' => 'mauvais-secret']);

        $response->assertStatus(401);
        $this->assertSame('deconnecte', $agent->fresh()->whatsapp_statut);
    }

    public function test_webhook_returns_404_for_unknown_agent(): void
    {
        $this->configurerConnecteur();

        $response = $this->postJson('/api/webhooks/whatsapp-connector', [
            'agentId' => 999999,
            'statut' => 'connecte',
        ], ['X-Connector-Secret' => 'test-secret']);

        $response->assertStatus(404);
    }
}
