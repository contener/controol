<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyConversationVisiteurRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\MessagerieTempsReelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de MesConversationsController -- vue "acheteur", jamais scopée par
 * la boutique courante (withoutGlobalScopes), filtrée sur visiteur_user_id.
 */
class MesConversationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Conversation::withoutGlobalScopes()
                ->where('visiteur_user_id', $request->user()->id)
                ->with([
                    'boutique:id,nom,slug,logo_path',
                    'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom', 'photo_path'),
                    'dernierMessage',
                ])
                ->orderByDesc('dernier_message_a')
                ->paginate(20)
                ->withQueryString()
        );
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $conv = Conversation::withoutGlobalScopes()
            ->where('visiteur_user_id', $request->user()->id)
            ->with([
                'boutique:id,nom,slug,logo_path',
                'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom', 'photo_path'),
                'messages',
            ])
            ->findOrFail($conversation);

        app(MessagerieTempsReelService::class)->marquerLus($conv, ConversationMessage::EXPEDITEUR_VISITEUR);

        return response()->json($conv);
    }

    /**
     * Fil ouvert côté acheteur (appelé toutes les 3 s) -- même forme que
     * Api\MessageController::synchroniser, limité aux conversations de l'utilisateur.
     */
    public function synchroniser(Request $request, int $conversation, MessagerieTempsReelService $tempsReel): JsonResponse
    {
        $conv = Conversation::withoutGlobalScopes()
            ->where('visiteur_user_id', $request->user()->id)
            ->findOrFail($conversation);

        return response()->json(
            $tempsReel->synchroniserFil($conv, ConversationMessage::EXPEDITEUR_VISITEUR, $request->integer('apres'))
        );
    }

    public function repondre(ReplyConversationVisiteurRequest $request, int $conversation): JsonResponse
    {
        $conv = Conversation::withoutGlobalScopes()->findOrFail($conversation);

        $message = $conv->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_VISITEUR,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conv->increment('messages_non_lus_boutique', 1, ['dernier_message_a' => now()]);

        return response()->json(['message' => 'Message envoyé.', 'envoye' => $message->fresh()]);
    }
}
