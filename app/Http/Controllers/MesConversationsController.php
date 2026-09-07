<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyConversationVisiteurRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vue "acheteur" : les conversations qu'un utilisateur Controool a lui-même engagées en tant
 * que visiteur, potentiellement avec plusieurs boutiques qui ne sont pas les siennes — jamais
 * scopée par currentBoutique, donc toujours withoutGlobalScopes() ici (voir §0 du plan).
 */
class MesConversationsController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = Conversation::withoutGlobalScopes()
            ->where('visiteur_user_id', $request->user()->id)
            ->with([
                'boutique:id,nom,slug,logo_path',
                'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom'),
                'dernierMessage',
            ])
            ->orderByDesc('dernier_message_a')
            ->paginate(20)
            ->withQueryString();

        $conversationActive = null;
        if ($request->filled('conversation')) {
            $conversationActive = Conversation::withoutGlobalScopes()
                ->where('visiteur_user_id', $request->user()->id)
                ->with([
                    'boutique:id,nom,slug',
                    'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom'),
                    'messages',
                ])
                ->find($request->integer('conversation'));

            if ($conversationActive && $conversationActive->messages_non_lus_visiteur > 0) {
                $conversationActive->update(['messages_non_lus_visiteur' => 0]);
            }
        }

        return Inertia::render('MesConversations/Index', [
            'conversations' => $conversations,
            'conversationActive' => $conversationActive,
        ]);
    }

    public function repondre(ReplyConversationVisiteurRequest $request, int $conversation): RedirectResponse
    {
        $conv = Conversation::withoutGlobalScopes()->findOrFail($conversation);

        $conv->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_VISITEUR,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conv->increment('messages_non_lus_boutique', 1, ['dernier_message_a' => now()]);

        return back()->with('flash_success', 'Message envoyé.');
    }
}
