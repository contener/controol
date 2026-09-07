<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyGuestConversationRequest;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\VisiteurIdentiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accès invité (non connecté) à UNE conversation précise via un lien signé Laravel — filet
 * de secours multi-appareil, l'accès normal se fait via le cookie posé sur la boutique
 * publique. Ne jamais type-hinter Conversation ici (le binding implicite appliquerait le
 * scope global BelongsToBoutique et casserait l'accès invité — voir §0 du plan).
 */
class GuestConversationController extends Controller
{
    public function show(Request $request, int $conversation, VisiteurIdentiteService $identite): Response
    {
        $conv = Conversation::withoutGlobalScopes()
            ->with([
                'boutique:id,nom,slug,logo_path',
                'produit' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nom'),
                'messages',
            ])
            ->findOrFail($conversation);

        $identite->synchroniserCookie($request, $conv);

        if ($conv->messages_non_lus_visiteur > 0) {
            $conv->update(['messages_non_lus_visiteur' => 0]);
        }

        return Inertia::render('Public/ConversationInvite', [
            'conversation' => $conv,
        ]);
    }

    public function repondre(ReplyGuestConversationRequest $request, int $conversation, VisiteurIdentiteService $identite): RedirectResponse
    {
        $conv = Conversation::withoutGlobalScopes()->findOrFail($conversation);
        $identite->verifierAcces($request, $conv);

        if ($conv->statut === Conversation::STATUT_BLOQUEE) {
            abort(403, "Cette conversation n'accepte plus de nouveaux messages.");
        }

        $conv->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_VISITEUR,
            'contenu' => $request->string('contenu')->toString(),
        ]);

        $extra = ['dernier_message_a' => now()];
        if (in_array($conv->statut, [Conversation::STATUT_FERMEE, Conversation::STATUT_ARCHIVEE], true)) {
            $extra['statut'] = Conversation::STATUT_OUVERTE;
        }
        $conv->increment('messages_non_lus_boutique', 1, $extra);

        return back()->with('flash_success', 'Message envoyé.');
    }
}
