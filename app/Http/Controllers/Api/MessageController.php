<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyConversationRequest;
use App\Http\Requests\StoreConversationDepuisClientRequest;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\MessagerieTempsReelService;
use App\Services\VisiteurIdentiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Équivalent API de MessageController -- `index` : boutique courante (comme le site) ;
 * `boite` : boîte de réception unifiée de l'app ; le fil (`{conversationGeree}`)
 * s'ouvre pour toute boutique dont l'utilisateur gère la messagerie. Même
 * Policy (ConversationPolicy, permission "Messagerie"), mêmes règles. La liste et le
 * fil sont deux appels séparés (écrans distincts sur mobile) au lieu du paramètre
 * ?conversation= de la page web.
 */
class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        return response()->json([
            'conversations' => Conversation::with(['produit:id,nom,photo_path', 'client:id,nom', 'dernierMessage'])
                ->orderByDesc('dernier_message_a')
                ->paginate(20)
                ->withQueryString(),
            'clients' => Client::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    /**
     * Boîte de réception unifiée de l'application (onglet Messages) : toutes les
     * conversations de l'utilisateur -- reçues par chacune des boutiques dont il gère
     * la messagerie (pas seulement la boutique courante) et celles qu'il a engagées
     * comme acheteur. `role` indique de quel côté il se trouve ("boutique" ou
     * "visiteur"), `non_lus` le compteur de ce côté. ?filtre=boutiques|achats.
     */
    public function boite(Request $request, MessagerieTempsReelService $tempsReel): JsonResponse
    {
        $user = $request->user();
        $boutiques = $tempsReel->boutiquesMessagerie($user);
        $filtre = $request->string('filtre')->toString();

        $page = Conversation::withoutGlobalScopes()
            ->where(function ($q) use ($boutiques, $user, $filtre) {
                if ($filtre !== 'achats') {
                    $q->whereIn('boutique_id', $boutiques);
                }
                if ($filtre !== 'boutiques') {
                    $q->orWhere(fn ($v) => $v->where('visiteur_user_id', $user->id)->whereNotIn('boutique_id', $boutiques));
                }
            })
            ->with([
                'boutique:id,nom,slug,logo_path',
                'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom', 'photo_path'),
                'client' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom'),
                'dernierMessage',
            ])
            ->orderByDesc('dernier_message_a')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Conversation $c) use ($boutiques) {
                $commeBoutique = $boutiques->contains($c->boutique_id);
                $c->setAttribute('role', $commeBoutique ? 'boutique' : 'visiteur');
                $c->setAttribute('non_lus', $commeBoutique ? $c->messages_non_lus_boutique : $c->messages_non_lus_visiteur);

                return $c;
            });

        return response()->json([
            'conversations' => $page,
            'gere_des_boutiques' => $boutiques->isNotEmpty(),
            'plusieurs_boutiques' => $boutiques->count() > 1,
        ]);
    }

    public function show(Conversation $conversation, MessagerieTempsReelService $tempsReel): JsonResponse
    {
        $this->authorize('view', $conversation);

        $tempsReel->marquerLus($conversation, ConversationMessage::EXPEDITEUR_BOUTIQUE);

        return response()->json($conversation->load([
            'boutique:id,nom,slug,logo_path',
            'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom', 'photo_path'),
            'client' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom'),
            'messages',
        ]));
    }

    /**
     * Fil ouvert dans l'application (appelé toutes les 3 s) : nouveaux messages du
     * visiteur, statut reçu/lu des réponses de la boutique, statut de la conversation.
     */
    public function synchroniser(Request $request, Conversation $conversation, MessagerieTempsReelService $tempsReel): JsonResponse
    {
        $this->authorize('view', $conversation);

        return response()->json(
            $tempsReel->synchroniserFil($conversation, ConversationMessage::EXPEDITEUR_BOUTIQUE, $request->integer('apres'))
        );
    }

    public function store(StoreConversationDepuisClientRequest $request, VisiteurIdentiteService $identite): JsonResponse
    {
        $client = Client::findOrFail($request->integer('client_id'));

        $conversation = Conversation::create([
            'produit_id' => $request->integer('produit_id') ?: null,
            'client_id' => $client->id,
            'visiteur_nom' => $client->nom,
            'visiteur_contact' => $client->email ?? $client->telephone,
            'statut' => Conversation::STATUT_OUVERTE,
        ]);

        $conversation->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_BOUTIQUE,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conversation->update(['dernier_message_a' => now()]);

        return response()->json([
            'message' => 'Conversation créée.',
            'conversation_id' => $conversation->id,
            'lien_conversation' => $identite->lienSigne($conversation),
        ], 201);
    }

    public function repondre(ReplyConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $conversation->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_BOUTIQUE,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conversation->increment('messages_non_lus_visiteur', 1, ['dernier_message_a' => now()]);

        return response()->json([
            'message' => 'Réponse envoyée.',
            'envoye' => $message->fresh(),
            'conversation' => $conversation->fresh()->load('messages'),
        ]);
    }

    public function updateStatut(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('updateStatut', $conversation);

        $data = $request->validate(['statut' => ['required', Rule::in(Conversation::STATUTS)]]);
        $conversation->update(['statut' => $data['statut']]);

        return response()->json(['message' => 'Statut de la conversation mis à jour.', 'statut' => $data['statut']]);
    }
}
