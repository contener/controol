<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ClientControllerTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_authenticated_user_can_list_and_create_clients_via_the_api(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/clients', ['nom' => 'Client API', 'etiquette' => 'prospect'])
            ->assertCreated()
            ->assertJsonPath('nom', 'Client API');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Client API');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/clients')->assertStatus(401);
    }

    public function test_user_cannot_view_a_client_from_another_boutique(): void
    {
        $proprietaireA = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaireB = $this->creerUtilisateurAvecBoutique('pro');
        $clientB = Client::create(['boutique_id' => $proprietaireB->current_boutique_id, 'nom' => 'Client B', 'etiquette' => 'client']);

        $this->actingAs($proprietaireA, 'sanctum')
            ->getJson("/api/clients/{$clientB->id}")
            ->assertStatus(403);
    }

    public function test_update_and_delete_a_client_via_the_api(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Avant', 'etiquette' => 'prospect']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/clients/{$client->id}", ['nom' => 'Après', 'etiquette' => 'client'])
            ->assertOk()
            ->assertJsonPath('nom', 'Après');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/clients/{$client->id}")
            ->assertOk();

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }
}
