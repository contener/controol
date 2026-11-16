<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Messagerie temps réel de l'app mobile et statuts façon WhatsApp (2026-11-16) :
 * envoyé (un trait) -> reçu quand le destinataire est connecté (deux traits) -> lu
 * quand il ouvre la conversation (deux traits bleus).
 */
class MessagerieTempsReelTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    /** @return array{0: User, 1: User, 2: Conversation} */
    private function conversation(): array
    {
        $vendeur = $this->creerUtilisateurAvecBoutique('pro');
        $acheteur = User::factory()->create();

        $conversation = Conversation::withoutGlobalScopes()->create([
            'boutique_id' => $vendeur->current_boutique_id,
            'visiteur_user_id' => $acheteur->id,
            'visiteur_nom' => $acheteur->name,
            'statut' => Conversation::STATUT_OUVERTE,
            'dernier_message_a' => now(),
        ]);

        return [$vendeur, $acheteur, $conversation];
    }

    public function test_message_passe_de_envoye_a_recu_puis_lu(): void
    {
        [$vendeur, $acheteur, $conversation] = $this->conversation();

        $id = $this->actingAs($acheteur, 'sanctum')
            ->postJson("/api/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Bonjour'])
            ->assertOk()
            ->json('envoye.id');

        $message = ConversationMessage::findOrFail($id);
        $this->assertNull($message->recu_le);
        $this->assertNull($message->lu_le);

        // Le vendeur ouvre l'application (synchronisation globale) : reçu.
        $this->actingAs($vendeur, 'sanctum')->getJson('/api/compte/messagerie/synchro')
            ->assertOk()
            ->assertJsonPath('compteurs.messages_non_lus', 1);
        $this->assertNotNull($message->fresh()->recu_le);
        $this->assertNull($message->fresh()->lu_le);

        // Le vendeur ouvre la conversation : lu, compteur remis à zéro.
        $this->actingAs($vendeur, 'sanctum')->getJson("/api/messages/{$conversation->id}")->assertOk();
        $this->assertNotNull($message->fresh()->lu_le);
        $this->assertSame(0, $conversation->fresh()->messages_non_lus_boutique);

        // L'acheteur voit les deux traits bleus via la synchro de son fil.
        $this->actingAs($acheteur, 'sanctum')
            ->getJson("/api/mes-conversations/{$conversation->id}/synchro?apres=0")
            ->assertOk()
            ->assertJsonPath('statuts.0.id', $id)
            ->assertJsonPath('statuts.0.lu_le', fn ($v) => $v !== null);
    }

    public function test_synchro_du_fil_renvoie_uniquement_les_nouveaux_messages(): void
    {
        [$vendeur, $acheteur, $conversation] = $this->conversation();

        $premier = $this->actingAs($vendeur, 'sanctum')
            ->postJson("/api/messages/{$conversation->id}/repondre", ['contenu' => 'Un'])->json('envoye.id');
        $this->actingAs($vendeur, 'sanctum')
            ->postJson("/api/messages/{$conversation->id}/repondre", ['contenu' => 'Deux'])->assertOk();

        $reponse = $this->actingAs($acheteur, 'sanctum')
            ->getJson("/api/mes-conversations/{$conversation->id}/synchro?apres={$premier}")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.contenu', 'Deux');

        // Le fil ouvert côté acheteur marque les messages du vendeur comme lus.
        $this->assertSame(0, ConversationMessage::whereNull('lu_le')->count());
        $this->assertSame(0, $conversation->fresh()->messages_non_lus_visiteur);
        $this->assertSame('ouverte', $reponse->json('statut'));
    }

    public function test_synchro_du_fil_refusee_a_un_tiers(): void
    {
        [, , $conversation] = $this->conversation();
        $intrus = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($intrus, 'sanctum')->getJson("/api/mes-conversations/{$conversation->id}/synchro")->assertNotFound();
        $this->actingAs($intrus, 'sanctum')->getJson("/api/messages/{$conversation->id}/synchro")->assertStatus(404);
    }

    public function test_synchro_globale_ne_marque_pas_les_messages_des_autres(): void
    {
        [$vendeur, , $conversation] = $this->conversation();
        $conversation->messages()->create(['expediteur' => 'visiteur', 'contenu' => 'Salut']);

        $tiers = $this->creerUtilisateurAvecBoutique('pro');
        $this->actingAs($tiers, 'sanctum')->getJson('/api/compte/messagerie/synchro')->assertOk();
        $this->assertSame(1, ConversationMessage::whereNull('recu_le')->count());

        // Le message que le vendeur a lui-même envoyé ne devient pas "reçu" par sa propre synchro.
        $conversation->messages()->create(['expediteur' => 'boutique', 'contenu' => 'Réponse']);
        $this->actingAs($vendeur, 'sanctum')->getJson('/api/compte/messagerie/synchro')->assertOk();
        $this->assertNull(ConversationMessage::where('expediteur', 'boutique')->first()->recu_le);
    }
}
