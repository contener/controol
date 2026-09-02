<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    public function test_client_can_be_created_with_boutique_scoping(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);

        $response = $this->post('/clients', [
            'nom' => 'Client Test',
            'email' => 'client@test.com',
            'etiquette' => 'prospect',
        ]);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', [
            'nom' => 'Client Test',
            'boutique_id' => $user->current_boutique_id,
            'etiquette' => 'prospect',
        ]);
    }

    public function test_client_requires_a_valid_etiquette(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user);

        $response = $this->post('/clients', [
            'nom' => 'Client Test',
            'etiquette' => 'invalide',
        ]);

        $response->assertSessionHasErrors('etiquette');
    }

    public function test_client_creation_is_blocked_beyond_plan_limit(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit', []);
        $user->planActif()->update(['limite_clients' => 1]);
        $this->actingAs($user);

        Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client existant', 'etiquette' => 'client']);

        $response = $this->post('/clients', [
            'nom' => 'Client en trop',
            'etiquette' => 'prospect',
        ]);

        $response->assertSessionHas('flash_error');
        $this->assertDatabaseMissing('clients', ['nom' => 'Client en trop']);
    }

    public function test_user_cannot_view_client_from_another_boutique(): void
    {
        $userA = $this->creerUtilisateurAvecBoutique();
        $userB = $this->creerUtilisateurAvecBoutique();

        $clientB = Client::create([
            'boutique_id' => $userB->current_boutique_id,
            'nom' => 'Client de B',
            'etiquette' => 'client',
        ]);

        $this->actingAs($userA);

        // Le scope global BelongsToBoutique rend le client d'une autre boutique invisible
        // à la résolution du modèle de route : 404 plutôt que 403 (évite de révéler
        // l'existence de la ressource à un utilisateur non autorisé).
        $response = $this->get("/clients/{$clientB->id}/edit");

        $response->assertNotFound();
    }

    public function test_user_cannot_update_client_from_another_boutique(): void
    {
        $userA = $this->creerUtilisateurAvecBoutique();
        $userB = $this->creerUtilisateurAvecBoutique();

        $clientB = Client::create([
            'boutique_id' => $userB->current_boutique_id,
            'nom' => 'Client de B',
            'etiquette' => 'client',
        ]);

        $this->actingAs($userA);

        $response = $this->put("/clients/{$clientB->id}", [
            'nom' => 'Client modifié',
            'etiquette' => 'client',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('clients', ['id' => $clientB->id, 'nom' => 'Client de B']);
    }

    public function test_authenticated_user_without_current_boutique_sees_no_cross_tenant_data(): void
    {
        $userA = $this->creerUtilisateurAvecBoutique();
        Client::create(['boutique_id' => $userA->current_boutique_id, 'nom' => 'Client de A', 'etiquette' => 'client']);

        $userSansBoutique = User::factory()->create(['current_boutique_id' => null]);
        $this->actingAs($userSansBoutique);

        // Sans boutique courante, la route est verrouillée par le middleware dédié...
        $this->get('/clients')->assertRedirect(route('boutiques.create'));

        // ...et même en interrogeant directement le modèle, aucune fuite cross-tenant.
        $this->assertSame(0, Client::count());
    }
}
