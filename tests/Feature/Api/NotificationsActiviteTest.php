<?php

namespace Tests\Feature\Api;

use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\NotificationUtilisateur;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Suivi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Notifications d'activité (2026-11-16) : messages, j'aime, abonnés, commandes,
 * boutiques, abonnement -- NotificationService, cloche du site et de l'application.
 */
class NotificationsActiviteTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    /** @return array{0: User, 1: Boutique, 2: Produit} */
    private function vendeur(): array
    {
        $vendeur = $this->creerUtilisateurAvecBoutique('pro');
        Plan::whereKey($vendeur->planActif()->id)->update(['marketplace' => true, 'audience' => true]);
        $boutique = $vendeur->currentBoutique;
        $boutique->update(['marketplace_visible' => true, 'statut' => 'active']);

        $produit = Produit::withoutGlobalScopes()->create([
            'boutique_id' => $boutique->id,
            'nom' => 'Chemise',
            'type' => 'produit',
            'prix_vente' => 5000,
            'actif' => true,
            'marketplace_visible' => true,
        ]);

        return [$vendeur, $boutique->fresh(), $produit];
    }

    private function notifications(User $user, string $type)
    {
        return NotificationUtilisateur::where('user_id', $user->id)->where('type', $type)->get();
    }

    public function test_creation_de_boutique_notifie_le_proprietaire(): void
    {
        [$vendeur] = $this->vendeur();

        $this->assertCount(1, $this->notifications($vendeur, 'boutique_creee'));
    }

    public function test_like_notifie_le_vendeur_une_seule_fois(): void
    {
        [$vendeur, $boutique, $produit] = $this->vendeur();
        $acheteur = User::factory()->create();

        $this->actingAs($acheteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/produits/{$produit->id}/jaime")->assertOk();
        $this->actingAs($acheteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/produits/{$produit->id}/jaime")->assertOk();

        $likes = $this->notifications($vendeur, 'like');
        $this->assertCount(1, $likes);
        $this->assertSame($boutique->id, $likes->first()->donnees['boutique_id']);
        $this->assertStringContainsString($acheteur->name, $likes->first()->message);
    }

    public function test_abonnement_et_reabonnement_notifient_le_vendeur(): void
    {
        [$vendeur, $boutique] = $this->vendeur();
        $acheteur = User::factory()->create();

        $this->actingAs($acheteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/suivre")->assertOk();
        $this->actingAs($acheteur, 'sanctum')->deleteJson("/api/marketplace/boutiques/{$boutique->slug}/suivre")->assertOk();
        $this->actingAs($acheteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/suivre")->assertOk();

        $this->assertCount(2, $this->notifications($vendeur, 'nouvel_abonne'));
    }

    public function test_commande_notifie_le_vendeur(): void
    {
        [$vendeur, $boutique, $produit] = $this->vendeur();
        $acheteur = User::factory()->create();

        $this->actingAs($acheteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/produits/{$produit->id}/commande")->assertOk();
        $this->postJson("/boutique/{$boutique->slug}/produits/{$produit->id}/commande")->assertOk();

        $this->assertCount(2, $this->notifications($vendeur, 'nouvelle_commande'));
    }

    public function test_messages_regroupes_par_conversation_tant_que_non_lus(): void
    {
        [$vendeur, $boutique] = $this->vendeur();
        $acheteur = User::factory()->create();
        $conversation = Conversation::withoutGlobalScopes()->create([
            'boutique_id' => $boutique->id,
            'visiteur_user_id' => $acheteur->id,
            'visiteur_nom' => $acheteur->name,
            'statut' => Conversation::STATUT_OUVERTE,
        ]);

        $this->actingAs($acheteur, 'sanctum')->postJson("/api/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Un'])->assertOk();
        $this->actingAs($acheteur, 'sanctum')->postJson("/api/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Deux'])->assertOk();

        $recus = $this->notifications($vendeur, 'message_recu');
        $this->assertCount(1, $recus);
        $this->assertStringContainsString('Deux', $recus->first()->message);
        $this->assertSame($conversation->id, $recus->first()->donnees['conversation_id']);

        // La réponse du vendeur notifie l'acheteur, pas le vendeur lui-même.
        $this->actingAs($vendeur, 'sanctum')->postJson("/api/messages/{$conversation->id}/repondre", ['contenu' => 'Merci'])->assertOk();
        $this->assertCount(1, $this->notifications($acheteur, 'message_vendeur'));
        $this->assertCount(0, $this->notifications($vendeur, 'message_vendeur'));
    }

    public function test_nouvelle_boutique_annoncee_aux_abonnes_du_vendeur(): void
    {
        [$vendeur, $boutique] = $this->vendeur();
        $acheteur = User::factory()->create();
        Suivi::create(['boutique_id' => $boutique->id, 'user_id' => $acheteur->id, 'notifications_actives' => true, 'abonne_a' => now()]);

        Boutique::create(['user_id' => $vendeur->id, 'nom' => 'Deuxième', 'slug' => 'deuxieme-'.uniqid(), 'devise' => 'XAF']);

        $this->assertCount(1, $this->notifications($acheteur, 'nouvelle_boutique'));
    }

    public function test_liste_filtree_par_categorie_avec_compteurs(): void
    {
        [$vendeur, $boutique, $produit] = $this->vendeur();
        $acheteur = User::factory()->create();
        $this->actingAs($acheteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/produits/{$produit->id}/jaime");
        NotificationUtilisateur::create(['user_id' => $vendeur->id, 'type' => 'annonce', 'titre' => 'T', 'message' => 'M']);

        $this->actingAs($vendeur, 'sanctum')->getJson('/api/notifications?categorie=jaime')
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.type', 'like')
            ->assertJsonPath('non_lues_par_categorie.jaime', 1)
            ->assertJsonPath('non_lues_par_categorie.autres', 1)
            ->assertJsonPath('non_lues_par_categorie.boutiques', 1);

        $this->actingAs($vendeur, 'sanctum')->getJson('/api/notifications?categorie=autres')
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.type', 'annonce');
    }
}
