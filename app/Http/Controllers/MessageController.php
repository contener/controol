<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyConversationRequest;
use App\Http\Requests\StoreConversationDepuisClientRequest;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\VisiteurIdentiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Conversation::class);

        $conversations = Conversation::with(['produit:id,nom', 'client:id,nom', 'dernierMessage'])
            ->orderByDesc('dernier_message_a')
            ->paginate(20)
            ->withQueryString();

        $conversationActive = null;
        if ($request->filled('conversation')) {
            $conversationActive = Conversation::with(['produit:id,nom', 'client:id,nom', 'messages'])
                ->find($request->integer('conversation'));

            if ($conversationActive) {
                $this->authorize('view', $conversationActive);

                if ($conversationActive->messages_non_lus_boutique > 0) {
                    $conversationActive->update(['messages_non_lus_boutique' => 0]);
                }
            }
        }

        return Inertia::render('Messages/Index', [
            'conversations' => $conversations,
            'conversationActive' => $conversationActive,
            'clients' => Client::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function store(StoreConversationDepuisClientRequest $request, VisiteurIdentiteService $identite): RedirectResponse
    {
        $this->authorize('create', Conversation::class);

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

        return back()->with('flash_success', 'Conversation créée.')
            ->with('flash_lien_conversation', $identite->lienSigne($conversation));
    }

    public function repondre(ReplyConversationRequest $request, Conversation $conversation): RedirectResponse
    {
        $conversation->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_BOUTIQUE,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conversation->increment('messages_non_lus_visiteur', 1, ['dernier_message_a' => now()]);

        return back()->with('flash_success', 'Réponse envoyée.');
    }

    public function updateStatut(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('updateStatut', $conversation);

        $data = $request->validate(['statut' => ['required', Rule::in(Conversation::STATUTS)]]);
        $conversation->update(['statut' => $data['statut']]);

        return back()->with('flash_success', 'Statut de la conversation mis à jour.');
    }
}
