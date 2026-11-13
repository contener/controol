<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AudienceInteraction;
use App\Models\AudienceMembre;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Produit;
use App\Services\AudienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Équivalent API d'AudienceController (web) -- mêmes requêtes, mêmes verrous (plan
 * Basique/Pro ET permission collaborateur "Audience"), mêmes statistiques réelles
 * affichées même quand l'accès est verrouillé (aperçu flouté côté mobile aussi).
 */
class AudienceController extends Controller
{
    private const STATUTS = ['actif', 'contacte', 'interesse', 'converti', 'desinscrit', 'bloque', 'archive'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $planAutorise = (bool) $user->planActif()?->audience;
        $permissionAutorisee = $user->currentBoutique?->autoriseAudience($user) ?? false;
        $autorise = $planAutorise && $permissionAutorisee;

        if (! $autorise) {
            return response()->json([
                'autorise' => false,
                'acces_refuse_par_proprietaire' => $planAutorise && ! $permissionAutorisee,
                'statistiques' => $this->statistiques(),
                'membres' => null,
                'produits_disponibles' => [],
                'statuts' => self::STATUTS,
            ]);
        }

        $recherche = $request->string('recherche')->toString();
        $produitId = $request->integer('produit_id') ?: null;
        $type = $request->string('type')->toString();
        $statutFiltre = $request->string('statut')->toString();

        $membres = AudienceMembre::query()
            ->with(['user:id,name', 'derniereInteraction.produit:id,nom'])
            ->withCount([
                'interactions',
                'interactions as produits_distincts_count' => fn ($q) => $q->select(DB::raw('count(distinct produit_id)')),
            ])
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($qq) use ($recherche) {
                    $qq->where('nom', 'like', "%{$recherche}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$recherche}%"));
                });
            })
            ->when($produitId, fn ($q) => $q->whereHas('interactions', fn ($iq) => $iq->where('produit_id', $produitId)))
            ->when($type !== '', fn ($q) => $q->whereHas('interactions', fn ($iq) => $iq->where('type', $type)))
            ->when($statutFiltre !== '', fn ($q) => $q->where('statut', $statutFiltre))
            ->orderByDesc(
                AudienceInteraction::select('created_at')
                    ->whereColumn('audience_membre_id', 'audience_membres.id')
                    ->latest('created_at')
                    ->limit(1)
            )
            ->paginate(15)
            ->withQueryString();

        $membres->getCollection()->transform(fn (AudienceMembre $m) => [
            'id' => $m->id,
            'nom' => $m->nomAffiche(),
            'est_anonyme' => $m->estAnonyme(),
            'statut' => $m->statut,
            'total_interactions' => $m->interactions_count,
            'total_produits' => $m->produits_distincts_count,
            'dernier_produit' => $m->derniereInteraction?->produit?->nom,
            'derniere_interaction_type' => $m->derniereInteraction?->type,
            'derniere_interaction_a' => $m->derniereInteraction?->created_at?->diffForHumans(),
        ]);

        return response()->json([
            'autorise' => true,
            'acces_refuse_par_proprietaire' => false,
            'statistiques' => $this->statistiques(),
            'membres' => $membres,
            'produits_disponibles' => Produit::query()->orderBy('nom')->get(['id', 'nom']),
            'statuts' => self::STATUTS,
        ]);
    }

    public function show(Request $request, AudienceMembre $membre): JsonResponse
    {
        $this->verifierAcces($request);

        $membre->load(['user:id,name', 'interactions' => fn ($q) => $q->with('produit:id,nom,photo_path')->orderByDesc('created_at')]);

        return response()->json([
            'membre' => [
                'id' => $membre->id,
                'nom' => $membre->nomAffiche(),
                'contact' => $membre->contactAffiche(),
                'est_anonyme' => $membre->estAnonyme(),
                'statut' => $membre->statut,
                'premiere_interaction_a' => optional($membre->interactions->min('created_at'))->format('d/m/Y'),
            ],
            'produits_consultes' => $membre->interactions->pluck('produit')->filter()->unique('id')->values(),
            'historique' => $membre->interactions->map(fn (AudienceInteraction $i) => [
                'id' => $i->id,
                'type' => $i->type,
                'produit_nom' => $i->produit?->nom,
                'created_at' => $i->created_at->format('d/m/Y H:i'),
            ]),
            'statuts' => self::STATUTS,
        ]);
    }

    /**
     * Comme le web : construit le lien wa.me côté serveur et journalise l'ouverture --
     * ouvrir WhatsApp prépare la relance, ne l'envoie jamais.
     */
    public function relancerWhatsapp(Request $request, AudienceMembre $membre, AudienceService $audience): JsonResponse
    {
        $this->verifierAcces($request);

        $numero = $membre->contactAffiche();
        abort_if(! $numero, 422, 'Aucun contact disponible pour cette personne.');

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $audience->enregistrerInteraction(
            $request->user()->currentBoutique,
            AudienceInteraction::WHATSAPP_RELANCE_OPENED,
            null,
            $membre->user_id ? ['user_id' => $membre->user_id] : ['visiteur_token' => $membre->visiteur_token],
        );

        $numeroPropre = ltrim(preg_replace('/[^\d+]/', '', $numero), '+');

        return response()->json(['lien' => "https://wa.me/{$numeroPropre}?text=".rawurlencode($data['message'])]);
    }

    public function relancerMessage(Request $request, AudienceMembre $membre, AudienceService $audience): JsonResponse
    {
        $this->verifierAcces($request);

        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        $boutique = $request->user()->currentBoutique;
        $identiteVisiteur = $membre->user_id
            ? ['visiteur_user_id' => $membre->user_id]
            : ['visiteur_token' => $membre->visiteur_token];

        $conversation = Conversation::where($identiteVisiteur)
            ->where('statut', Conversation::STATUT_OUVERTE)
            ->orderByDesc('dernier_message_a')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'produit_id' => $membre->derniereInteraction?->produit_id,
                ...$identiteVisiteur,
                'visiteur_nom' => $membre->nomAffiche(),
                'visiteur_contact' => $membre->contactAffiche(),
                'statut' => Conversation::STATUT_OUVERTE,
            ]);
        }

        $conversation->messages()->create([
            'expediteur' => ConversationMessage::EXPEDITEUR_BOUTIQUE,
            'contenu' => $data['message'],
        ]);
        $conversation->increment('messages_non_lus_visiteur', 1, ['dernier_message_a' => now()]);

        $audience->enregistrerInteraction(
            $boutique,
            AudienceInteraction::MESSAGE_RELANCE_ENVOYE,
            $conversation->produit_id ? Produit::withoutGlobalScopes()->find($conversation->produit_id) : null,
            $membre->user_id ? ['user_id' => $membre->user_id] : ['visiteur_token' => $membre->visiteur_token],
        );

        return response()->json(['message' => 'Message envoyé.']);
    }

    public function updateStatut(Request $request, AudienceMembre $membre): JsonResponse
    {
        $this->verifierAcces($request);

        $data = $request->validate(['statut' => ['required', 'string', Rule::in(self::STATUTS)]]);

        $membre->update($data);

        return response()->json(['message' => 'Statut mis à jour.']);
    }

    private function verifierAcces(Request $request): void
    {
        abort_unless((bool) $request->user()->planActif()?->audience, 403, "L'audience détaillée nécessite le plan Basique ou Pro.");
        abort_unless((bool) $request->user()->currentBoutique?->autoriseAudience($request->user()), 403, "Vous n'avez pas accès à l'audience. Demandez l'accès au propriétaire de la boutique.");
    }

    private function statistiques(): array
    {
        return [
            'total' => AudienceMembre::count(),
            'likes' => AudienceInteraction::where('type', AudienceInteraction::LIKE)->distinct('audience_membre_id')->count('audience_membre_id'),
            'messages' => AudienceInteraction::where('type', AudienceInteraction::MESSAGE)->distinct('audience_membre_id')->count('audience_membre_id'),
            'nouvelles_7_jours' => AudienceMembre::where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }
}
