<?php

namespace Tests\Feature\Api;

use App\Models\AudienceMembre;
use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\NotificationUtilisateur;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\Suivi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Endpoints API ajoutés pour la refonte complète de l'app mobile (2026-11-13) --
 * Compte, Notifications, Marketplace, Audience, Équipe, Messages, Mes conversations,
 * Abonnement, Parrainage, gestion des boutiques. Voir controol.md §5.21.
 */
class EspaceMobileTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function activerFonctionnalites(User $user, array $flags): void
    {
        Plan::whereKey($user->planActif()->id)->update($flags);
    }

    private function boutiquePublique(): Boutique
    {
        $vendeur = $this->creerUtilisateurAvecBoutique('pro');
        $this->activerFonctionnalites($vendeur, ['marketplace' => true, 'audience' => true]);
        $boutique = $vendeur->currentBoutique;
        $boutique->update(['marketplace_visible' => true, 'statut' => 'active', 'categorie' => 'Mode', 'ville' => 'Douala']);

        Produit::withoutGlobalScopes()->create([
            'boutique_id' => $boutique->id,
            'nom' => 'Chemise',
            'type' => 'produit',
            'prix_vente' => 5000,
            'actif' => true,
            'marketplace_visible' => true,
        ]);

        return $boutique->fresh();
    }

    public function test_resume_returns_navigation_data(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        NotificationUtilisateur::create(['user_id' => $user->id, 'type' => 'info', 'titre' => 'T', 'message' => 'M']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/compte/resume')
            ->assertOk()
            ->assertJsonPath('boutique_courante_id', $user->current_boutique_id)
            ->assertJsonPath('compteurs.notifications_non_lues', 1)
            ->assertJsonPath('boutiques.0.permissions.dashboard', true);
    }

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $notification = NotificationUtilisateur::create(['user_id' => $user->id, 'type' => 'info', 'titre' => 'T', 'message' => 'M']);
        $autre = User::factory()->create();
        $notificationAutre = NotificationUtilisateur::create(['user_id' => $autre->id, 'type' => 'info', 'titre' => 'T', 'message' => 'M']);

        $this->actingAs($user, 'sanctum')->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'notifications');
        $this->actingAs($user, 'sanctum')->patchJson("/api/notifications/{$notification->id}/lu")->assertOk();
        $this->actingAs($user, 'sanctum')->patchJson("/api/notifications/{$notificationAutre->id}/lu")->assertForbidden();

        $this->assertNotNull($notification->fresh()->lu_a);
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->forceFill(['password' => Hash::make('ancien-mot-de-passe')])->save();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/compte/mot-de-passe', ['current_password' => 'faux', 'password' => 'NouveauMotDePasse123!', 'password_confirmation' => 'NouveauMotDePasse123!'])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/compte/mot-de-passe', ['current_password' => 'ancien-mot-de-passe', 'password' => 'NouveauMotDePasse123!', 'password_confirmation' => 'NouveauMotDePasse123!'])
            ->assertOk();

        $this->assertTrue(Hash::check('NouveauMotDePasse123!', $user->fresh()->password));
    }

    public function test_preferences_update(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/compte/preferences', ['locale' => 'en', 'theme' => 'dark'])
            ->assertOk();

        $this->assertSame('dark', $user->fresh()->theme);
    }

    public function test_marketplace_lists_eligible_shops_and_shows_one(): void
    {
        $boutique = $this->boutiquePublique();
        $visiteur = $this->creerUtilisateurAvecBoutique('gratuit');

        $this->actingAs($visiteur, 'sanctum')
            ->getJson('/api/marketplace')
            ->assertOk()
            ->assertJsonPath('boutiques.data.0.slug', $boutique->slug);

        $this->actingAs($visiteur, 'sanctum')
            ->getJson("/api/marketplace/boutiques/{$boutique->slug}")
            ->assertOk()
            ->assertJsonPath('boutique.nom', $boutique->nom)
            ->assertJsonCount(1, 'produits')
            ->assertJsonPath('est_abonne', false);
    }

    public function test_follow_like_and_message_a_shop_then_reply_as_both_sides(): void
    {
        $boutique = $this->boutiquePublique();
        $produit = Produit::withoutGlobalScopes()->where('boutique_id', $boutique->id)->first();
        $visiteur = $this->creerUtilisateurAvecBoutique('gratuit');

        $this->actingAs($visiteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/suivre")->assertOk();
        $this->assertTrue(Suivi::where('boutique_id', $boutique->id)->where('user_id', $visiteur->id)->whereNull('desabonne_a')->exists());

        $this->actingAs($visiteur, 'sanctum')->postJson("/api/marketplace/boutiques/{$boutique->slug}/produits/{$produit->id}/jaime")->assertOk();

        $this->actingAs($visiteur, 'sanctum')
            ->postJson("/api/marketplace/boutiques/{$boutique->slug}/messages", [
                'nom_visiteur' => $visiteur->name,
                'contenu' => 'Bonjour, disponible ?',
                'produit_id' => $produit->id,
            ])
            ->assertCreated();

        $conversation = Conversation::withoutGlobalScopes()->where('visiteur_user_id', $visiteur->id)->firstOrFail();
        $this->assertSame(1, $conversation->messages_non_lus_boutique);

        // Le propriétaire voit et répond depuis sa messagerie.
        $proprietaire = $boutique->proprietaire;
        $this->actingAs($proprietaire, 'sanctum')->getJson('/api/messages')->assertOk()->assertJsonPath('conversations.data.0.id', $conversation->id);
        $this->actingAs($proprietaire, 'sanctum')->getJson("/api/messages/{$conversation->id}")->assertOk()->assertJsonCount(1, 'messages');
        $this->actingAs($proprietaire, 'sanctum')->postJson("/api/messages/{$conversation->id}/repondre", ['contenu' => 'Oui !'])->assertOk();

        // L'acheteur la retrouve dans "Mes conversations".
        $this->actingAs($visiteur, 'sanctum')->getJson('/api/mes-conversations')->assertOk()->assertJsonPath('data.0.id', $conversation->id);
        $this->actingAs($visiteur, 'sanctum')->getJson("/api/mes-conversations/{$conversation->id}")->assertOk()->assertJsonCount(2, 'messages');
        $this->actingAs($visiteur, 'sanctum')->postJson("/api/mes-conversations/{$conversation->id}/repondre", ['contenu' => 'Merci'])->assertOk();

        // Interactions journalisées dans l'audience du vendeur.
        $this->actingAs($proprietaire, 'sanctum')
            ->getJson('/api/audience')
            ->assertOk()
            ->assertJsonPath('autorise', true)
            ->assertJsonPath('statistiques.likes', 1)
            ->assertJsonPath('statistiques.messages', 1);
    }

    public function test_blocked_visitor_cannot_message_again(): void
    {
        $boutique = $this->boutiquePublique();
        $visiteur = $this->creerUtilisateurAvecBoutique('gratuit');

        Conversation::withoutGlobalScopes()->create([
            'boutique_id' => $boutique->id,
            'visiteur_user_id' => $visiteur->id,
            'visiteur_nom' => 'X',
            'statut' => Conversation::STATUT_BLOQUEE,
        ]);

        $this->actingAs($visiteur, 'sanctum')
            ->postJson("/api/marketplace/boutiques/{$boutique->slug}/messages", ['nom_visiteur' => 'X', 'contenu' => 'Salut'])
            ->assertForbidden();
    }

    public function test_audience_is_locked_without_plan_and_detail_works_with_it(): void
    {
        $gratuit = $this->creerUtilisateurAvecBoutique('gratuit');
        $this->actingAs($gratuit, 'sanctum')->getJson('/api/audience')->assertOk()->assertJsonPath('autorise', false);

        $boutique = $this->boutiquePublique();
        $proprietaire = $boutique->proprietaire;
        $membre = AudienceMembre::withoutGlobalScopes()->create(['boutique_id' => $boutique->id, 'nom' => 'Alice', 'contact' => '+237600000000']);

        $this->actingAs($proprietaire, 'sanctum')->getJson("/api/audience/{$membre->id}")->assertOk()->assertJsonPath('membre.nom', 'Alice');
        $this->actingAs($proprietaire, 'sanctum')
            ->postJson("/api/audience/{$membre->id}/whatsapp", ['message' => 'Bonjour'])
            ->assertOk()
            ->assertJsonPath('lien', 'https://wa.me/237600000000?text=Bonjour');
        $this->actingAs($proprietaire, 'sanctum')->patchJson("/api/audience/{$membre->id}/statut", ['statut' => 'contacte'])->assertOk();
        $this->assertSame('contacte', $membre->fresh()->statut);
    }

    public function test_owner_manages_team_with_plan(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $this->activerFonctionnalites($proprietaire, ['personnel_boutique' => true]);

        $this->actingAs($proprietaire, 'sanctum')
            ->postJson('/api/equipe', [
                'name' => 'Employé',
                'email' => 'employe@example.com',
                'password' => 'MotDePasse123!',
                'acces_clients' => true,
            ])
            ->assertCreated();

        $collaborateur = User::where('email', 'employe@example.com')->firstOrFail();

        $this->actingAs($proprietaire, 'sanctum')
            ->getJson('/api/equipe')
            ->assertOk()
            ->assertJsonPath('collaborateurs.0.permissions.acces_clients', true)
            ->assertJsonPath('collaborateurs.0.permissions.acces_factures', false);

        $this->actingAs($proprietaire, 'sanctum')->patchJson("/api/equipe/{$collaborateur->id}", ['acces_factures' => true])->assertOk();
        $this->actingAs($proprietaire, 'sanctum')->deleteJson("/api/equipe/{$collaborateur->id}")->assertOk();
        $this->assertNull($collaborateur->fresh()->current_boutique_id);
    }

    public function test_collaborator_cannot_manage_team(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique('pro');
        $collaborateur = User::factory()->create();
        $proprietaire->currentBoutique->collaborateurs()->attach($collaborateur->id);
        $collaborateur->forceFill(['current_boutique_id' => $proprietaire->current_boutique_id])->save();

        $this->actingAs($collaborateur->fresh(), 'sanctum')->getJson('/api/equipe')->assertForbidden();
    }

    public function test_subscription_page_and_paid_plan_request(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $plan = Plan::create([
            'code' => 'basique', 'nom' => 'Basique', 'prix' => 5000, 'devise' => 'XAF',
            'lien_paiement' => 'https://paiement.example/abc', 'ordre' => 2,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/abonnement')->assertOk()->assertJsonStructure(['plan_actif', 'usage', 'plans']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/abonnement/plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('lien_paiement', 'https://paiement.example/abc');

        $this->assertDatabaseHas('paiements', ['user_id' => $user->id, 'statut' => 'en_attente']);
    }

    public function test_referral_page(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/parrainage')
            ->assertOk()
            ->assertJsonStructure(['lien_parrainage', 'statistiques', 'filleuls', 'historique']);
    }

    public function test_shop_management_and_marketplace_lock(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $boutique = $user->currentBoutique;

        $this->actingAs($user, 'sanctum')->getJson('/api/boutiques/gestion')->assertOk()->assertJsonPath('boutiques.0.id', $boutique->id);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/boutiques/{$boutique->id}", ['nom' => 'Nouveau nom', 'ville' => 'Yaoundé'])
            ->assertOk();
        $this->assertSame('Nouveau nom', $boutique->fresh()->nom);

        // Plan sans Marketplace : jamais publiée, même requête forgée.
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/boutiques/{$boutique->id}/marketplace", ['marketplace_visible' => true])
            ->assertStatus(422);
        $this->assertFalse((bool) $boutique->fresh()->marketplace_visible);

        $autre = $this->creerUtilisateurAvecBoutique('pro');
        $this->actingAs($autre, 'sanctum')->getJson("/api/boutiques/{$boutique->id}")->assertForbidden();
    }

    public function test_publishing_a_product_notifies_followers(): void
    {
        $boutique = $this->boutiquePublique();
        $proprietaire = $boutique->proprietaire;
        $abonne = User::factory()->create();
        Suivi::create(['boutique_id' => $boutique->id, 'user_id' => $abonne->id, 'notifications_actives' => true, 'abonne_a' => now()]);

        $produit = Produit::withoutGlobalScopes()->create([
            'boutique_id' => $boutique->id, 'nom' => 'Robe', 'type' => 'produit', 'prix_vente' => 8000, 'actif' => true, 'marketplace_visible' => false,
        ]);

        $this->actingAs($proprietaire, 'sanctum')
            ->patchJson("/api/produits/{$produit->id}/marketplace", ['marketplace_visible' => true])
            ->assertOk();

        $this->assertDatabaseHas('notifications_utilisateurs', ['user_id' => $abonne->id, 'type' => 'nouveau_produit']);
    }

    public function test_global_view(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');

        $this->actingAs($user, 'sanctum')->getJson('/api/compte/vue-globale')->assertOk()->assertJsonStructure(['stats' => ['boutiques', 'chiffre_affaires']]);
    }

    /**
     * Un produit est visible d'office à sa création (2026-11-13), abonnés notifiés,
     * et reste désactivable ensuite.
     */
    public function test_new_product_is_visible_immediately_and_can_be_hidden(): void
    {
        $boutique = $this->boutiquePublique();
        $proprietaire = $boutique->proprietaire;
        $abonne = User::factory()->create();
        Suivi::create(['boutique_id' => $boutique->id, 'user_id' => $abonne->id, 'notifications_actives' => true, 'abonne_a' => now()]);

        $id = $this->actingAs($proprietaire, 'sanctum')
            ->postJson('/api/produits', ['type' => 'service', 'nom' => 'Retouche', 'prix_vente' => 2000, 'unite' => 'pièce'])
            ->assertCreated()
            ->assertJsonPath('marketplace_visible', true)
            ->json('id');

        $this->assertDatabaseHas('notifications_utilisateurs', ['user_id' => $abonne->id, 'type' => 'nouveau_produit']);

        $this->actingAs($proprietaire, 'sanctum')
            ->getJson("/api/marketplace/boutiques/{$boutique->slug}")
            ->assertJsonFragment(['nom' => 'Retouche']);

        $this->actingAs($proprietaire, 'sanctum')->patchJson("/api/produits/{$id}/marketplace", ['marketplace_visible' => false])->assertOk();
        $this->assertFalse((bool) Produit::withoutGlobalScopes()->find($id)->marketplace_visible);
    }

    /** Une nouvelle boutique est publiée d'office si le plan le permet, jamais sinon. */
    public function test_new_shop_is_visible_only_when_plan_allows_it(): void
    {
        $avecPlan = $this->creerUtilisateurAvecBoutique('pro');
        $this->activerFonctionnalites($avecPlan, ['marketplace' => true, 'limite_boutiques' => null]);
        $this->actingAs($avecPlan, 'sanctum')->postJson('/api/boutiques', ['nom' => 'Visible'])->assertCreated()->assertJsonPath('boutique.marketplace_visible', true);

        $sansPlan = $this->creerUtilisateurAvecBoutique('gratuit');
        $this->actingAs($sansPlan, 'sanctum')->postJson('/api/boutiques', ['nom' => 'Masquee'])->assertCreated()->assertJsonPath('boutique.marketplace_visible', false);
    }

    /**
     * Cas réel du 2026-11-13 : produit enregistré inactif mais "visible" -- absent de
     * la boutique publique. "Activer" depuis l'app doit le rendre réellement affiché.
     */
    public function test_activating_an_inactive_product_makes_it_appear_in_the_shop(): void
    {
        $boutique = $this->boutiquePublique();
        $proprietaire = $boutique->proprietaire;

        $id = $this->actingAs($proprietaire, 'sanctum')
            ->postJson('/api/produits', ['type' => 'service', 'nom' => 'Inactif', 'prix_vente' => 1000, 'unite' => 'pièce', 'actif' => false])
            ->assertCreated()
            ->json('id');

        $this->actingAs($proprietaire, 'sanctum')->getJson("/api/marketplace/boutiques/{$boutique->slug}")->assertJsonMissing(['nom' => 'Inactif']);

        $this->actingAs($proprietaire, 'sanctum')
            ->patchJson("/api/produits/{$id}/marketplace", ['marketplace_visible' => true])
            ->assertOk()
            ->assertJsonPath('produit.actif', true)
            ->assertJsonPath('produit.marketplace_visible', true);

        $this->actingAs($proprietaire, 'sanctum')->getJson("/api/marketplace/boutiques/{$boutique->slug}")->assertJsonFragment(['nom' => 'Inactif']);
    }
}
