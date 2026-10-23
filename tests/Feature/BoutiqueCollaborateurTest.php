<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class BoutiqueCollaborateurTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Employé Test',
            'email' => 'employe@example.com',
            'telephone' => '+237600000000',
            'password' => 'mot-de-passe-solide',
        ], $overrides);
    }

    public function test_creating_a_collaborator_is_blocked_without_the_plan_flag(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('gratuit', []);

        $response = $this->actingAs($proprietaire)->post('/boutique/collaborateurs', $this->payload());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'employe@example.com']);
    }

    public function test_owner_can_create_a_collaborator_with_the_plan_flag(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);

        $response = $this->actingAs($proprietaire)->post('/boutique/collaborateurs', $this->payload());

        $response->assertRedirect();
        $collaborateur = User::where('email', 'employe@example.com')->firstOrFail();
        $this->assertNotNull($collaborateur->email_verified_at);
        $this->assertSame($proprietaire->current_boutique_id, $collaborateur->current_boutique_id);
        $this->assertDatabaseHas('boutique_collaborateurs', [
            'boutique_id' => $proprietaire->current_boutique_id,
            'user_id' => $collaborateur->id,
        ]);
    }

    public function test_a_collaborator_cannot_create_another_collaborator(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);
        $collaborateur = User::factory()->create();
        $proprietaire->currentBoutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $proprietaire->current_boutique_id])->save();

        $response = $this->actingAs($collaborateur)->post('/boutique/collaborateurs', $this->payload(['email' => 'autre@example.com']));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'autre@example.com']);
    }

    public function test_collaborator_inherits_owners_plan_for_feature_gating(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['marketplace' => true]);
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $this->assertTrue((bool) $collaborateur->fresh()->planActif()?->marketplace);
    }

    public function test_collaborator_can_manage_clients_of_the_boutique(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $response = $this->actingAs($collaborateur)->post('/clients', [
            'nom' => 'Client ajouté par le collaborateur',
            'etiquette' => 'prospect',
        ]);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', ['nom' => 'Client ajouté par le collaborateur', 'boutique_id' => $boutique->id]);
    }

    public function test_collaborator_cannot_update_or_delete_the_boutique_itself(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $this->actingAs($collaborateur)->put("/boutiques/{$boutique->id}", ['nom' => 'Nouveau nom', 'devise' => 'XAF'])->assertStatus(403);
        $this->actingAs($collaborateur)->delete("/boutiques/{$boutique->id}")->assertStatus(403);
    }

    public function test_removing_a_collaborator_revokes_access_immediately(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $response = $this->actingAs($proprietaire)->delete("/boutique/collaborateurs/{$collaborateur->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('boutique_collaborateurs', ['boutique_id' => $boutique->id, 'user_id' => $collaborateur->id]);
        $this->assertNull($collaborateur->fresh()->current_boutique_id);

        // current_boutique_id a été remis à null -- EnsureBoutiqueSelected redirige
        // avant même d'atteindre la Policy, confirmant que l'accès est bien coupé.
        // actingAs() réutilise l'instance PHP fournie telle quelle (ne la refetch jamais
        // depuis la base) -- fresh() indispensable pour refléter la mise à jour faite par
        // la requête DELETE précédente.
        Client::create(['boutique_id' => $boutique->id, 'nom' => 'Client X', 'etiquette' => 'client']);
        $this->actingAs($collaborateur->fresh())->get('/clients')->assertRedirect(route('boutiques.create'));
    }

    public function test_collaborator_from_another_boutique_cannot_be_removed_by_a_different_owner(): void
    {
        $proprietaireA = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaireB = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $proprietaireB->currentBoutique->collaborateurs()->attach($collaborateur->id);

        $response = $this->actingAs($proprietaireA)->delete("/boutique/collaborateurs/{$collaborateur->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('boutique_collaborateurs', ['boutique_id' => $proprietaireB->current_boutique_id, 'user_id' => $collaborateur->id]);
    }
}
