<?php

namespace Tests\Feature\Api;

use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Onglet Messages de l'app (2026-11-16) : toutes les conversations de l'utilisateur,
 * reçues par chacune de ses boutiques et engagées comme acheteur.
 */
class MessagerieUnifieeTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function conversation(int $boutiqueId, ?int $visiteurId = null, int $nonLusBoutique = 0, int $nonLusVisiteur = 0): Conversation
    {
        return Conversation::withoutGlobalScopes()->create([
            'boutique_id' => $boutiqueId,
            'visiteur_user_id' => $visiteurId,
            'visiteur_nom' => 'Visiteur',
            'statut' => Conversation::STATUT_OUVERTE,
            'messages_non_lus_boutique' => $nonLusBoutique,
            'messages_non_lus_visiteur' => $nonLusVisiteur,
            'dernier_message_a' => now(),
        ]);
    }

    public function test_boite_reunit_toutes_les_boutiques_et_les_achats(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $deuxieme = Boutique::create(['user_id' => $user->id, 'nom' => 'Deuxième', 'slug' => 'deuxieme-'.uniqid(), 'devise' => 'XAF']);
        $vendeur = $this->creerUtilisateurAvecBoutique('pro');
        $tiers = $this->creerUtilisateurAvecBoutique('pro');

        $this->conversation($user->current_boutique_id, nonLusBoutique: 1);
        $this->conversation($deuxieme->id, nonLusBoutique: 2);
        $this->conversation($vendeur->current_boutique_id, $user->id, nonLusVisiteur: 1);
        $this->conversation($tiers->current_boutique_id);

        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/messagerie')->assertOk()
            ->assertJsonCount(3, 'conversations.data')
            ->assertJsonPath('plusieurs_boutiques', true);

        $roles = collect($reponse->json('conversations.data'))->pluck('role')->countBy();
        $this->assertSame(2, $roles['boutique']);
        $this->assertSame(1, $roles['visiteur']);

        $this->actingAs($user, 'sanctum')->getJson('/api/messagerie?filtre=achats')->assertJsonCount(1, 'conversations.data');
        $this->actingAs($user, 'sanctum')->getJson('/api/messagerie?filtre=boutiques')->assertJsonCount(2, 'conversations.data');

        $this->actingAs($user, 'sanctum')->getJson('/api/compte/messagerie/synchro')
            ->assertJsonPath('compteurs.messages_non_lus', 3);
    }

    public function test_fil_d_une_autre_boutique_geree_s_ouvre_sans_changer_de_boutique(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $deuxieme = Boutique::create(['user_id' => $user->id, 'nom' => 'Deuxième', 'slug' => 'deuxieme-'.uniqid(), 'devise' => 'XAF']);
        $conversation = $this->conversation($deuxieme->id, nonLusBoutique: 1);

        $this->actingAs($user, 'sanctum')->getJson("/api/messages/{$conversation->id}")->assertOk()
            ->assertJsonPath('boutique.nom', 'Deuxième');
        $this->actingAs($user, 'sanctum')->postJson("/api/messages/{$conversation->id}/repondre", ['contenu' => 'Bonjour'])->assertOk();
        $this->actingAs($user, 'sanctum')->getJson("/api/messages/{$conversation->id}/synchro")->assertOk();

        $this->assertSame($user->current_boutique_id, $user->fresh()->current_boutique_id);
    }

    public function test_fil_d_une_boutique_non_geree_reste_interdit(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $autre = $this->creerUtilisateurAvecBoutique('pro');
        $conversation = $this->conversation($autre->current_boutique_id);

        $this->actingAs($user, 'sanctum')->getJson("/api/messages/{$conversation->id}")->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson("/api/messages/{$conversation->id}/repondre", ['contenu' => 'X'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson("/api/messages/{$conversation->id}/synchro")->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson("/api/messages/{$conversation->id}/statut", ['statut' => 'bloquee'])->assertForbidden();
    }

    public function test_collaborateur_sans_permission_messagerie_ne_voit_pas_la_boutique(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $proprietaire->currentBoutique->collaborateurs()->attach($collaborateur->id, ['acces_messagerie' => false]);
        $conversation = $this->conversation($proprietaire->current_boutique_id);

        $this->actingAs($collaborateur, 'sanctum')->getJson('/api/messagerie')->assertJsonCount(0, 'conversations.data');
        $this->actingAs($collaborateur, 'sanctum')->getJson("/api/messages/{$conversation->id}")->assertForbidden();
    }
}
