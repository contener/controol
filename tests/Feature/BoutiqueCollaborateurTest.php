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

        $response = $this->actingAs($proprietaire)->post('/equipe', $this->payload());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'employe@example.com']);
    }

    public function test_owner_can_create_a_collaborator_with_the_plan_flag(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);

        $response = $this->actingAs($proprietaire)->post('/equipe', $this->payload());

        $response->assertRedirect();
        $collaborateur = User::where('email', 'employe@example.com')->firstOrFail();
        $this->assertNotNull($collaborateur->email_verified_at);
        $this->assertSame($proprietaire->current_boutique_id, $collaborateur->current_boutique_id);
        $this->assertDatabaseHas('boutique_collaborateurs', [
            'boutique_id' => $proprietaire->current_boutique_id,
            'user_id' => $collaborateur->id,
        ]);
    }

    public function test_owner_can_grant_permissions_at_creation_time(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);

        $response = $this->actingAs($proprietaire)->post('/equipe', $this->payload([
            'acces_produits' => true,
            'acces_factures' => true,
        ]));

        $response->assertRedirect();
        $collaborateur = User::where('email', 'employe@example.com')->firstOrFail();
        $this->assertDatabaseHas('boutique_collaborateurs', [
            'boutique_id' => $proprietaire->current_boutique_id,
            'user_id' => $collaborateur->id,
            'acces_produits' => true,
            'acces_factures' => true,
            // Tout ce qui n'a pas été coché explicitement reste à false -- jamais
            // d'accès automatique même pour les permissions omises du formulaire.
            'acces_clients' => false,
            'acces_depenses' => false,
            'acces_dashboard' => false,
            'acces_audience' => false,
            'acces_messagerie' => false,
        ]);
    }

    public function test_a_collaborator_cannot_create_another_collaborator(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);
        $collaborateur = User::factory()->create();
        $proprietaire->currentBoutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $proprietaire->current_boutique_id])->save();

        $response = $this->actingAs($collaborateur)->post('/equipe', $this->payload(['email' => 'autre@example.com']));

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

    public function test_collaborator_can_manage_clients_of_the_boutique_when_granted_the_permission(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id, ['acces_clients' => true]);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $response = $this->actingAs($collaborateur)->post('/clients', [
            'nom' => 'Client ajouté par le collaborateur',
            'etiquette' => 'prospect',
        ]);

        $response->assertRedirect('/clients');
        $this->assertDatabaseHas('clients', ['nom' => 'Client ajouté par le collaborateur', 'boutique_id' => $boutique->id]);
    }

    /**
     * Cœur de la demande "pas d'accès automatique" : un collaborateur ajouté sans
     * qu'aucune permission ne soit cochée ne doit avoir accès à rien, contrairement
     * au comportement v1 (accès complet et automatique dès l'ajout).
     */
    public function test_collaborator_without_any_permission_cannot_access_clients_produits_factures_or_dashboard(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $this->actingAs($collaborateur)->post('/clients', ['nom' => 'X', 'etiquette' => 'prospect'])->assertStatus(403);
        $this->actingAs($collaborateur)->get('/produits')->assertStatus(403);
        $this->actingAs($collaborateur)->get('/factures')->assertStatus(403);
        $this->actingAs($collaborateur)->get('/depenses')->assertStatus(403);

        // Le Dashboard est la page d'accueil post-connexion -- jamais un 403 brut,
        // mais un état "accès refusé" affiché normalement (200).
        $this->actingAs($collaborateur)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('accesRefuse', true));
    }

    public function test_owner_can_grant_a_specific_permission_to_an_existing_collaborator(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $this->actingAs($collaborateur)->get('/produits')->assertStatus(403);

        $response = $this->actingAs($proprietaire)->patch("/equipe/{$collaborateur->id}", ['acces_produits' => true]);
        $response->assertRedirect();

        $this->assertDatabaseHas('boutique_collaborateurs', [
            'boutique_id' => $boutique->id,
            'user_id' => $collaborateur->id,
            'acces_produits' => true,
            'acces_clients' => false,
        ]);
        $this->actingAs($collaborateur->fresh())->get('/produits')->assertOk();
    }

    public function test_a_collaborator_cannot_update_permissions(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $proprietaire->planActif()->update(['personnel_boutique' => true]);
        $collaborateur = User::factory()->create();
        $boutique = $proprietaire->currentBoutique;
        $boutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $boutique->id])->save();

        $response = $this->actingAs($collaborateur)->patch("/equipe/{$collaborateur->id}", ['acces_produits' => true]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('boutique_collaborateurs', ['boutique_id' => $boutique->id, 'user_id' => $collaborateur->id, 'acces_produits' => false]);
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

        $response = $this->actingAs($proprietaire)->delete("/equipe/{$collaborateur->id}");

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

        $response = $this->actingAs($proprietaireA)->delete("/equipe/{$collaborateur->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('boutique_collaborateurs', ['boutique_id' => $proprietaireB->current_boutique_id, 'user_id' => $collaborateur->id]);
    }
}
