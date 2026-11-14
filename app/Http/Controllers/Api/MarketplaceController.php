<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationMessageRequest;
use App\Models\AudienceInteraction;
use App\Models\AudienceMembre;
use App\Models\Boutique;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Produit;
use App\Models\Suivi;
use App\Services\AudienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace et page publique d'une boutique pour l'app mobile -- équivalent de
 * MarketplaceController::index() et PublicBoutiqueController (web). L'app est
 * toujours authentifiée (jeton) : l'identité visiteur est donc toujours l'utilisateur
 * connecté, jamais le cookie visiteur anonyme du web (VisiteurIdentiteService).
 *
 * envoyerMessage()/aimer() reprennent la branche "utilisateur connecté" de
 * PublicBoutiqueController::envoyerMessage()/aimerProduit() -- mêmes règles (blocage
 * sur toute la relation, réutilisation du fil ouvert, journalisation Audience). Si
 * ces règles évoluent côté web, mettre à jour LES DEUX côtés (voir controol.md §5.21).
 */
class MarketplaceController extends Controller
{
    private const CHAMPS_LISTE = ['id', 'nom', 'slug', 'logo_path', 'banniere_path', 'categorie', 'ville', 'pays'];

    /** Mêmes champs publics que PublicBoutiqueController::CHAMPS_PUBLICS. */
    private const CHAMPS_PUBLICS = [
        'id', 'nom', 'slug', 'logo_path', 'banniere_path', 'description', 'categorie',
        'adresse', 'ville', 'pays', 'telephone', 'whatsapp', 'email', 'devise',
        'facebook_url', 'instagram_url', 'telegram_url',
    ];

    public function index(Request $request): JsonResponse
    {
        $boutiques = Boutique::eligiblesMarketplace()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhereHas('produits', function ($pq) use ($recherche) {
                            $pq->withoutGlobalScopes()->where('actif', true)->where('marketplace_visible', true)->where('nom', 'like', "%{$recherche}%");
                        });
                });
            })
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->when($request->string('ville')->toString(), fn ($q, $v) => $q->where('ville', $v))
            ->withCount(['produits' => fn ($q) => $q->withoutGlobalScopes()->where('actif', true)->where('marketplace_visible', true)])
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn ($boutique) => [
                ...$boutique->only(self::CHAMPS_LISTE),
                'produits_count' => $boutique->produits_count,
            ]);

        $boutiqueIdsEligibles = Boutique::eligiblesMarketplace()->pluck('id');

        return response()->json([
            'boutiques' => $boutiques,
            'categories' => Boutique::eligiblesMarketplace()->whereNotNull('categorie')->distinct()->pluck('categorie'),
            'villes' => Boutique::eligiblesMarketplace()->whereNotNull('ville')->distinct()->pluck('ville'),
            'promotions' => Produit::withoutGlobalScopes()
                ->whereIn('boutique_id', $boutiqueIdsEligibles)
                ->where('actif', true)
                ->where('marketplace_visible', true)
                ->whereNotNull('promotion_prix')
                ->with('boutique:id,nom,slug,devise')
                ->latest()
                ->limit(8)
                ->get(['id', 'boutique_id', 'nom', 'photo_path', 'prix_vente', 'promotion_prix', 'mini_characteristics']),
            'plan_autorise_marketplace' => (bool) $request->user()->planActif()?->marketplace,
            'marketplace_url' => route('marketplace.index'),
        ]);
    }

    public function boutique(Request $request, string $slug): JsonResponse
    {
        $boutique = $this->resoudreBoutique($slug);
        $user = $request->user();

        $produits = Produit::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->where('marketplace_visible', true)
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->select(Produit::CHAMPS_PUBLICS)
            ->withCount(['interactionsAudience as likes_count' => fn ($q) => $q->withoutGlobalScopes()->where('type', AudienceInteraction::LIKE)])
            ->orderBy('nom')
            ->get();

        $membre = AudienceMembre::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'boutique' => $boutique->only(self::CHAMPS_PUBLICS),
            'produits' => $produits,
            'produits_aimes' => $membre
                ? AudienceInteraction::withoutGlobalScopes()
                    ->where('audience_membre_id', $membre->id)
                    ->where('type', AudienceInteraction::LIKE)
                    ->pluck('produit_id')->filter()->values()
                : [],
            'categories' => Produit::withoutGlobalScopes()
                ->where('boutique_id', $boutique->id)
                ->where('actif', true)
                ->where('marketplace_visible', true)
                ->whereNotNull('categorie')
                ->distinct()
                ->pluck('categorie'),
            'nombre_abonnes' => $boutique->nombreAbonnes(),
            'est_abonne' => Suivi::where('boutique_id', $boutique->id)->where('user_id', $user->id)->whereNull('desabonne_a')->exists(),
            'est_proprietaire' => $boutique->estGereePar($user),
            'url' => route('public.boutique', $boutique->slug),
        ]);
    }

    public function suivre(Request $request, string $slug): JsonResponse
    {
        $boutique = $this->resoudreBoutique($slug);
        $user = $request->user();

        if ($boutique->user_id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas suivre votre propre boutique.'], 422);
        }

        $suivi = Suivi::firstOrNew(['boutique_id' => $boutique->id, 'user_id' => $user->id]);
        $suivi->notifications_actives = true;
        $suivi->abonne_a = now();
        $suivi->desabonne_a = null;
        $suivi->save();

        return response()->json(['message' => "Vous suivez désormais {$boutique->nom}.", 'nombre_abonnes' => $boutique->nombreAbonnes()]);
    }

    public function neplusSuivre(Request $request, string $slug): JsonResponse
    {
        $boutique = $this->resoudreBoutique($slug);

        Suivi::where('boutique_id', $boutique->id)
            ->where('user_id', $request->user()->id)
            ->update(['desabonne_a' => now()]);

        return response()->json(['message' => "Vous ne suivez plus {$boutique->nom}.", 'nombre_abonnes' => $boutique->nombreAbonnes()]);
    }

    public function aimer(Request $request, string $slug, int $produit, AudienceService $audience): JsonResponse
    {
        $boutique = $this->resoudreBoutique($slug);

        $produitModele = Produit::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->where('marketplace_visible', true)
            ->findOrFail($produit);

        $audience->enregistrerInteraction($boutique, AudienceInteraction::LIKE, $produitModele, ['user_id' => $request->user()->id]);

        return response()->json(['aime' => true]);
    }

    /**
     * Fil existant entre l'utilisateur et cette boutique pour un produit donné (ou
     * sans produit) -- affiché dans la fenêtre "Contacter la boutique" comme sur le
     * web (ContactVendeurModal.vue). Marque les réponses du vendeur comme lues.
     */
    public function conversation(Request $request, string $slug): JsonResponse
    {
        $boutique = $this->resoudreBoutique($slug);
        $produitId = $request->integer('produit_id') ?: null;

        $conversation = Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('visiteur_user_id', $request->user()->id)
            ->where('produit_id', $produitId)
            ->with('messages')
            ->orderByDesc('dernier_message_a')
            ->first();

        if ($conversation && $conversation->messages_non_lus_visiteur > 0) {
            $conversation->update(['messages_non_lus_visiteur' => 0]);
        }

        return response()->json(['conversation' => $conversation]);
    }

    public function envoyerMessage(StoreConversationMessageRequest $request, string $slug, AudienceService $audience): JsonResponse
    {
        $boutique = $this->resoudreBoutique($slug);
        $user = $request->user();

        $produit = $request->filled('produit_id')
            ? Produit::withoutGlobalScopes()->where('boutique_id', $boutique->id)->find($request->integer('produit_id'))
            : null;

        $identiteVisiteur = ['visiteur_user_id' => $user->id];

        $bloque = Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where($identiteVisiteur)
            ->where('statut', Conversation::STATUT_BLOQUEE)
            ->exists();

        if ($bloque) {
            return response()->json(['message' => 'Vous ne pouvez plus contacter ce vendeur.'], 403);
        }

        $conversation = Conversation::withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('produit_id', $produit?->id)
            ->where($identiteVisiteur)
            ->where('statut', Conversation::STATUT_OUVERTE)
            ->first();

        if (! $conversation) {
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

        if ($produit) {
            $audience->enregistrerInteraction(
                $boutique,
                AudienceInteraction::MESSAGE,
                $produit,
                ['user_id' => $user->id],
                $request->string('nom_visiteur')->toString() ?: null,
                $request->string('contact_visiteur')->toString() ?: null,
            );
        }

        return response()->json([
            'message' => 'Votre message a bien été envoyé au vendeur.',
            'conversation' => Conversation::withoutGlobalScopes()->with('messages')->find($conversation->id),
        ], 201);
    }

    private function resoudreBoutique(string $slug): Boutique
    {
        return Boutique::where('slug', $slug)->where('statut', 'active')->firstOrFail();
    }
}
