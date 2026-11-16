<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\NotificationUtilisateur;
use App\Models\Paiement;
use App\Models\Produit;
use App\Models\Suivi;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Point d'entrée unique des notifications "activité" (cloche du site et de l'app
 * mobile) : messages, j'aime, nouveaux abonnés, commandes, boutiques, abonnement.
 * Chaque événement est branché à un seul endroit (événement de modèle ou service
 * déjà partagé web/API), jamais dans chaque contrôleur.
 *
 * Destinataires côté boutique : le propriétaire et les collaborateurs qui ont la
 * permission concernée (Messagerie pour les messages, Audience pour le reste) --
 * jamais l'auteur de l'action lui-même.
 */
class NotificationService
{
    /** Message posté dans une conversation (visiteur -> boutique ou boutique -> visiteur). */
    public function nouveauMessage(ConversationMessage $message): void
    {
        $conversation = Conversation::withoutGlobalScopes()->with('boutique')->find($message->conversation_id);
        $boutique = $conversation?->boutique;
        if (! $conversation || ! $boutique) {
            return;
        }

        $extrait = Str::limit($message->contenu, 120);

        if ($message->expediteur === ConversationMessage::EXPEDITEUR_VISITEUR) {
            $auteur = $conversation->visiteur_nom ?: 'Un visiteur';
            foreach ($this->equipe($boutique, 'acces_messagerie', exclure: $conversation->visiteur_user_id) as $userId) {
                $this->regrouper($userId, 'message_recu', $conversation->id, [
                    'titre' => "Nouveau message de {$auteur}",
                    'message' => "{$boutique->nom} : {$extrait}",
                    'lien' => route('messages.index', ['conversation' => $conversation->id]),
                    'donnees' => ['boutique_id' => $boutique->id, 'conversation_id' => $conversation->id],
                ]);
            }

            return;
        }

        if ($conversation->visiteur_user_id) {
            $this->regrouper($conversation->visiteur_user_id, 'message_vendeur', $conversation->id, [
                'titre' => "Réponse de {$boutique->nom}",
                'message' => $extrait,
                'lien' => route('mes-conversations.index', ['conversation' => $conversation->id]),
                'donnees' => ['boutique_id' => $boutique->id, 'conversation_id' => $conversation->id, 'slug' => $boutique->slug],
            ]);
        }
    }

    public function nouveauLike(Boutique $boutique, ?Produit $produit, ?int $auteurId): void
    {
        $auteur = $auteurId ? User::find($auteurId)?->name : null;

        $this->notifierEquipe($boutique, 'acces_audience', $auteurId, [
            'type' => 'like',
            'titre' => '❤️ Nouveau « J\'aime »',
            'message' => ($auteur ?: 'Un visiteur').' aime '.($produit ? "« {$produit->nom} »" : 'votre boutique')." ({$boutique->nom}).",
            'lien' => route('audience.index'),
            'donnees' => ['boutique_id' => $boutique->id, 'produit_id' => $produit?->id],
        ]);
    }

    public function nouvelAbonne(Suivi $suivi): void
    {
        $boutique = Boutique::find($suivi->boutique_id);
        $abonne = User::find($suivi->user_id);
        if (! $boutique || ! $abonne) {
            return;
        }

        $this->notifierEquipe($boutique, 'acces_audience', $abonne->id, [
            'type' => 'nouvel_abonne',
            'titre' => 'Nouvel abonné',
            'message' => "{$abonne->name} suit maintenant {$boutique->nom} ({$boutique->nombreAbonnes()} abonné".($boutique->nombreAbonnes() > 1 ? 's' : '').').',
            'lien' => route('audience.index'),
            'donnees' => ['boutique_id' => $boutique->id, 'user_id' => $abonne->id],
        ]);
    }

    /**
     * Clic sur "Commander" (ouverture de WhatsApp vers le vendeur) : la commande se
     * conclut sur WhatsApp, l'application n'en voit que l'intention.
     */
    public function nouvelleCommande(Boutique $boutique, Produit $produit, ?int $auteurId): void
    {
        $auteur = $auteurId ? User::find($auteurId)?->name : null;
        $prix = number_format((float) ($produit->promotion_prix ?? $produit->prix_vente), 0, ',', ' ');

        $this->notifierEquipe($boutique, 'acces_audience', $auteurId, [
            'type' => 'nouvelle_commande',
            'titre' => '🛒 Nouvelle commande',
            'message' => ($auteur ?: 'Un visiteur')." souhaite commander « {$produit->nom} » ({$prix} {$boutique->devise}) via WhatsApp.",
            'lien' => route('audience.index'),
            'donnees' => ['boutique_id' => $boutique->id, 'produit_id' => $produit->id],
        ]);
    }

    /**
     * Boutique créée : confirmation au propriétaire, et annonce aux abonnés de ses
     * autres boutiques (ils suivent déjà ce vendeur).
     */
    public function nouvelleBoutique(Boutique $boutique): void
    {
        $lien = route('public.boutique', $boutique->slug);
        $donnees = ['boutique_id' => $boutique->id, 'slug' => $boutique->slug];

        $this->creer($boutique->user_id, [
            'type' => 'boutique_creee',
            'titre' => 'Boutique créée',
            'message' => "Votre boutique « {$boutique->nom} » est prête. Ajoutez vos produits pour la faire découvrir.",
            'lien' => $lien,
            'donnees' => $donnees,
        ]);

        $abonnes = Suivi::query()
            ->whereIn('boutique_id', Boutique::where('user_id', $boutique->user_id)->where('id', '!=', $boutique->id)->select('id'))
            ->whereNull('desabonne_a')
            ->where('notifications_actives', true)
            ->where('user_id', '!=', $boutique->user_id)
            ->distinct()
            ->pluck('user_id');

        $vendeur = User::find($boutique->user_id)?->name ?? 'Un vendeur que vous suivez';
        foreach ($abonnes as $userId) {
            $this->creer($userId, [
                'type' => 'nouvelle_boutique',
                'titre' => 'Nouvelle boutique',
                'message' => "{$vendeur} vient d'ouvrir une nouvelle boutique : « {$boutique->nom} ».",
                'lien' => $lien,
                'donnees' => $donnees,
            ]);
        }
    }

    public function paiementTraite(Paiement $paiement): void
    {
        $plan = $paiement->abonnement?->plan?->nom ?? 'votre abonnement';
        $montant = number_format((float) $paiement->montant, 0, ',', ' ')." {$paiement->devise}";
        $approuve = $paiement->statut === Paiement::STATUT_APPROUVE;

        $this->creer($paiement->user_id, [
            'type' => $approuve ? 'paiement_approuve' : 'paiement_rejete',
            'titre' => $approuve ? '✅ Abonnement activé' : 'Paiement refusé',
            'message' => $approuve
                ? "Votre paiement de {$montant} a été confirmé : le plan {$plan} est actif."
                : "Votre paiement de {$montant} pour le plan {$plan} n'a pas été validé".($paiement->motif_rejet ? " : {$paiement->motif_rejet}" : '.'),
            'lien' => route('abonnement.index'),
            'donnees' => ['paiement_id' => $paiement->id],
        ]);
    }

    public function collaborateurAjoute(Boutique $boutique, User $collaborateur): void
    {
        $this->creer($collaborateur->id, [
            'type' => 'collaborateur_ajoute',
            'titre' => 'Accès à une boutique',
            'message' => "Vous avez été ajouté comme collaborateur de « {$boutique->nom} ».",
            'lien' => route('dashboard'),
            'donnees' => ['boutique_id' => $boutique->id],
        ]);
    }

    /** @param  array<string, mixed>  $notification */
    private function notifierEquipe(Boutique $boutique, string $permission, ?int $auteurId, array $notification): void
    {
        foreach ($this->equipe($boutique, $permission, $auteurId) as $userId) {
            $this->creer($userId, $notification);
        }
    }

    /** @return Collection<int, int> */
    private function equipe(Boutique $boutique, string $permission, ?int $exclure = null): Collection
    {
        return collect([$boutique->user_id])
            ->concat($boutique->collaborateurs()->wherePivot($permission, true)->pluck('users.id'))
            ->unique()
            ->reject(fn ($id) => $exclure !== null && $id === $exclure)
            ->values();
    }

    /**
     * Une conversation active ne doit pas remplir la cloche d'une notification par
     * message : tant que la précédente n'est pas lue, elle est mise à jour (dernier
     * message, remontée en tête) au lieu d'en créer une nouvelle.
     *
     * @param  array<string, mixed>  $notification
     */
    private function regrouper(int $userId, string $type, int $conversationId, array $notification): void
    {
        $existante = NotificationUtilisateur::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('lu_a')
            ->where('donnees->conversation_id', $conversationId)
            ->first();

        if ($existante) {
            $existante->forceFill([...$notification, 'created_at' => now()])->save();

            return;
        }

        $this->creer($userId, ['type' => $type, ...$notification]);
    }

    /** @param  array<string, mixed>  $notification */
    private function creer(int $userId, array $notification): void
    {
        NotificationUtilisateur::create([
            'user_id' => $userId,
            'est_promotionnelle' => false,
            ...$notification,
        ]);
    }
}
