<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyConversationVisiteurRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
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

        if ($conv->messages_non_lus_visiteur > 0) {
            $conv->update(['messages_non_lus_visiteur' => 0]);
        }

        return response()->json($conv);
    }

    public function repondre(ReplyConversationVisiteurRequest $request, int $conversation): JsonResponse
    {
        $conv = Conversation::withoutGlobalScopes()->findOrFail($conversation);

        $conv->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_VISITEUR,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conv->increment('messages_non_lus_boutique', 1, ['dernier_message_a' => now()]);

        return response()->json(['message' => 'Message envoyé.']);
    }
}
