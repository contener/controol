<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Messagerie "en temps réel" et statuts de remise façon WhatsApp.
 *
 * L'hébergement mutualisé n'offre pas de WebSocket : l'application synchronise par
 * de courts appels réguliers (fil ouvert toutes les 3 s, synchronisation globale
 * toutes les 5 s). Le statut d'un message est dérivé de deux dates, jamais d'une
 * colonne "statut" en double :
 *  - envoyé : recu_le et lu_le à null (un trait) ;
 *  - reçu   : recu_le renseigné -- le destinataire est connecté (deux traits gris) ;
 *  - lu     : lu_le renseigné -- le destinataire a ouvert la conversation (deux traits bleus).
 */
class MessagerieTempsReelService
{
    /**
     * Le destinataire est connecté : tous les messages qui lui sont adressés et pas
     * encore remis passent à "reçu". Côté acheteur, les conversations où il est le
     * visiteur ; côté vendeur, celles des boutiques dont il gère la messagerie.
     */
    public function marquerRecus(User $user): void
    {
        $maintenant = now();

        ConversationMessage::query()
            ->whereNull('recu_le')
            ->where('expediteur', ConversationMessage::EXPEDITEUR_BOUTIQUE)
            ->whereIn('conversation_id', Conversation::withoutGlobalScopes()->select('id')->where('visiteur_user_id', $user->id))
            ->update(['recu_le' => $maintenant]);

        $boutiques = $this->boutiquesMessagerie($user);

        if ($boutiques->isNotEmpty()) {
            ConversationMessage::query()
                ->whereNull('recu_le')
                ->where('expediteur', ConversationMessage::EXPEDITEUR_VISITEUR)
                ->whereIn('conversation_id', Conversation::withoutGlobalScopes()->select('id')->whereIn('boutique_id', $boutiques))
                ->update(['recu_le' => $maintenant]);
        }
    }

    /**
     * Le [lecteur] ('boutique' ou 'visiteur') a la conversation sous les yeux : les
     * messages de l'autre partie passent à "lu" (et "reçu" s'ils ne l'étaient pas).
     */
    public function marquerLus(Conversation $conversation, string $lecteur): void
    {
        $maintenant = now();

        ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('expediteur', '!=', $lecteur)
            ->whereNull('lu_le')
            ->update(['lu_le' => $maintenant]);

        ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('expediteur', '!=', $lecteur)
            ->whereNull('recu_le')
            ->update(['recu_le' => $maintenant]);

        $compteur = $lecteur === ConversationMessage::EXPEDITEUR_BOUTIQUE ? 'messages_non_lus_boutique' : 'messages_non_lus_visiteur';

        if ($conversation->{$compteur} > 0) {
            $conversation->update([$compteur => 0]);
        }
    }

    /**
     * Réponse de synchronisation d'un fil ouvert : nouveaux messages après
     * [$apresId] et statut à jour des messages envoyés par [lecteur] (les 100
     * derniers suffisent : seuls ceux visibles à l'écran changent de statut).
     *
     * @return array{statut: string, messages: Collection, statuts: Collection}
     */
    public function synchroniserFil(Conversation $conversation, string $lecteur, int $apresId): array
    {
        $this->marquerLus($conversation, $lecteur);

        return [
            'statut' => $conversation->statut,
            'messages' => $conversation->messages()->where('id', '>', $apresId)->get(),
            'statuts' => ConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->where('expediteur', $lecteur)
                ->latest('id')
                ->limit(100)
                ->get(['id', 'recu_le', 'lu_le']),
        ];
    }

    /**
     * Boutiques dont l'utilisateur gère la messagerie : les siennes, et celles où il
     * est collaborateur avec la permission Messagerie.
     *
     * @return Collection<int, int>
     */
    public function boutiquesMessagerie(User $user): Collection
    {
        return Boutique::query()->where('user_id', $user->id)->pluck('id')
            ->concat(
                $user->boutiquesCollaborateur()->wherePivot('acces_messagerie', true)->pluck('boutiques.id')
            )
            ->unique()
            ->values();
    }
}
