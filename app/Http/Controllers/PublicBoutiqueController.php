<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationMessageRequest;
use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Produit;
use App\Services\VisiteurIdentiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicBoutiqueController extends Controller
{
    /**
     * Champs réellement destinés à être publics. On ne sérialise jamais le modèle
     * Boutique brut ici : user_id, marketplace_visible, marketplace_disabled_by_admin,
     * taux_tva_defaut ne doivent jamais transiter vers une page anonyme.
     */
    private const CHAMPS_PUBLICS = [
        'id', 'nom', 'slug', 'logo_path', 'banniere_path', 'description', 'categorie',
        'adresse', 'ville', 'pays', 'telephone', 'whatsapp', 'email', 'devise',
        'facebook_url', 'instagram_url', 'telegram_url',
    ];

    public function show(string $slug, Request $request, VisiteurIdentiteService $identite): Response
    {
        $boutique = $this->resoudreBoutique($slug);

        $produits = $boutique->produits()
            ->withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->orderBy('nom')
            ->get();

        $categories = $boutique->produits()
            ->withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->whereNotNull('categorie')
            ->distinct()
            ->pluck('categorie');

        return Inertia::render('Public/Boutique', [
            'boutique' => $boutique->only(self::CHAMPS_PUBLICS),
            'produits' => $produits,
            'categories' => $categories,
            'filtres' => $request->only('categorie'),
            'meta' => $this->meta($boutique),
            'mesConversations' => $this->mesConversations($boutique, $request),
            'conversationActive' => $this->conversationActive($boutique, $request, $identite),
        ]);
    }

    public function envoyerMessage(StoreConversationMessageRequest $request, string $slug, VisiteurIdentiteService $identite): RedirectResponse
    {
        $boutique = $this->resoudreBoutique($slug);

        // Un produit forgé appartenant à une autre boutique ne doit jamais rattacher le
        // message à un mauvais vendeur — on le résout uniquement dans le périmètre de
        // cette boutique, sinon on laisse le message sans produit associé.
        $produit = $request->filled('produit_id')
            ? Produit::withoutGlobalScopes()->where('boutique_id', $boutique->id)->find($request->integer('produit_id'))
            : null;

        $identiteVisiteur = Auth::check()
            ? ['visiteur_user_id' => Auth::id()]
            : ['visiteur_token' => $identite->resoudreOuCreerJeton($request)];

        // Un blocage s'applique à toute la relation boutique/visiteur, pas à un seul fil —
        // sinon changer de produit suffirait à contourner un blocage.
        $bloque = Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where($identiteVisiteur)
            ->where('statut', Conversation::STATUT_BLOQUEE)
            ->exists();

        if ($bloque) {
            return back()->with('flash_error', 'Vous ne pouvez plus contacter ce vendeur.');
        }

        $conversation = Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('produit_id', $produit?->id)
            ->where($identiteVisiteur)
            ->where('statut', Conversation::STATUT_OUVERTE)
            ->first();

        $nouvelle = ! $conversation;
        if ($nouvelle) {
            $conversation = Conversation::create([
                'boutique_id' => $boutique->id,
                'produit_id' => $produit?->id,
                ...$identiteVisiteur,
                'visiteur_nom' => $request->string('nom_visiteur')->toString(),
                'visiteur_contact' => $request->string('contact_visiteur')->toString() ?: null,
                'statut' => Conversation::STATUT_OUVERTE,
            ]);
        }

        $conversation->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_VISITEUR,
            'contenu' => $request->string('contenu')->toString(),
        ]);
        $conversation->increment('messages_non_lus_boutique', 1, ['dernier_message_a' => now()]);

        $reponse = back()->with('flash_success', 'Votre message a bien été envoyé au vendeur.');

        if ($nouvelle && ! Auth::check()) {
            $reponse = $reponse->with('flash_lien_conversation', $identite->lienSigne($conversation));
        }

        return $reponse;
    }

    private function mesConversations(Boutique $boutique, Request $request)
    {
        $jeton = $request->cookie(VisiteurIdentiteService::COOKIE_TOKEN);

        if (! Auth::check() && ! $jeton) {
            return collect();
        }

        return Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->when(Auth::check(), fn ($q) => $q->where('visiteur_user_id', Auth::id()))
            ->when(! Auth::check(), fn ($q) => $q->where('visiteur_token', $jeton))
            ->get(['id', 'produit_id', 'statut', 'dernier_message_a']);
    }

    private function conversationActive(Boutique $boutique, Request $request, VisiteurIdentiteService $identite): ?Conversation
    {
        if (! $request->filled('conversation')) {
            return null;
        }

        $candidat = Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->with('messages')
            ->find($request->integer('conversation'));

        if (! $candidat || ! $identite->aAcces($request, $candidat)) {
            return null;
        }

        if ($candidat->messages_non_lus_visiteur > 0) {
            $candidat->update(['messages_non_lus_visiteur' => 0]);
        }

        return $candidat;
    }

    private function resoudreBoutique(string $slug): Boutique
    {
        return Boutique::where('slug', $slug)->where('statut', 'active')->firstOrFail();
    }

    private function meta(Boutique $boutique): array
    {
        $image = $boutique->banniere_path ?? $boutique->logo_path;

        return [
            'title' => $boutique->nom,
            'description' => $boutique->description
                ? Str::limit(strip_tags($boutique->description), 160)
                : "Découvrez {$boutique->nom} et ses produits.",
            'image' => $image ? Storage::disk('public')->url($image) : null,
            'url' => route('public.boutique', $boutique->slug),
            'type' => 'website',
        ];
    }
}
