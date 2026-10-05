<?php

namespace App\Http\Controllers;

use App\Models\AudienceInteraction;
use App\Models\AudienceMembre;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Produit;
use App\Services\AudienceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AudienceController extends Controller
{
    private const STATUTS = ['actif', 'contacte', 'interesse', 'converti', 'desinscrit', 'bloque', 'archive'];

    /**
     * Toutes les requêtes ci-dessous (AudienceMembre::query()/count()...) sont
     * automatiquement scopées à la boutique courante par BelongsToBoutique -- jamais
     * besoin de filtrer boutique_id à la main dans ce contrôleur, contrairement aux
     * méthodes publiques de PublicBoutiqueController qui, elles, bypassent ce scope.
     */
    public function index(Request $request): Response
    {
        $autorise = (bool) $request->user()->planActif()?->audience;
        $statistiques = $this->statistiques();

        if (! $autorise) {
            return Inertia::render('Audience/Index', [
                'autorise' => false,
                'statistiques' => $statistiques,
                'membres' => null,
                'filtres' => [],
                'produitsDisponibles' => [],
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

        $membres->getCollection()->transform(fn (AudienceMembre $m) => $this->formaterMembre($m));

        return Inertia::render('Audience/Index', [
            'autorise' => true,
            'statistiques' => $statistiques,
            'membres' => $membres,
            'filtres' => $request->only(['recherche', 'produit_id', 'type', 'statut']),
            'produitsDisponibles' => Produit::query()->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function show(Request $request, AudienceMembre $membre): Response|RedirectResponse
    {
        if (! $request->user()->planActif()?->audience) {
            return redirect()->route('audience.index')->with('flash_error', "L'audience détaillée nécessite le plan Basique ou Pro.");
        }

        $membre->load(['user:id,name', 'interactions' => fn ($q) => $q->with('produit:id,nom,photo_path')->orderByDesc('created_at')]);

        return Inertia::render('Audience/Show', [
            'membre' => [
                'id' => $membre->id,
                'nom' => $membre->nomAffiche(),
                'contact' => $membre->contactAffiche(),
                'est_anonyme' => $membre->estAnonyme(),
                'statut' => $membre->statut,
                'premiere_interaction_a' => optional($membre->interactions->min('created_at'))->format('d/m/Y'),
            ],
            'produitsConsultes' => $membre->interactions->pluck('produit')->filter()->unique('id')->values(),
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
     * Construit le lien wa.me côté serveur -- ouvrir WhatsApp signifie uniquement que
     * la relance a été préparée, jamais que le message a été envoyé (cahier des
     * charges §10). Journalise l'ouverture comme une interaction de plus, sans table
     * d'audit séparée.
     */
    public function relancerWhatsapp(Request $request, AudienceMembre $membre, AudienceService $audience): JsonResponse
    {
        abort_unless((bool) $request->user()->planActif()?->audience, 403, "Cette fonctionnalité nécessite le plan Basique ou Pro.");

        $numero = $membre->contactAffiche();
        abort_if(! $numero, 422, "Aucun contact disponible pour cette personne.");

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

    /**
     * Relance par message interne (conversations) -- deuxième canal, disponible pour
     * tout le monde (connecté ou anonyme via jeton), contrairement à WhatsApp qui
     * nécessite un numéro renseigné. Réutilise le système de conversations existant
     * plutôt que d'en recréer un : reprend le fil ouvert le plus récent avec cette
     * personne s'il existe, sinon en ouvre un nouveau (rattaché à son dernier produit
     * consulté si connu). Contrairement à la relance WhatsApp, le message est
     * réellement envoyé ici (pas seulement "préparé").
     */
    public function relancerMessage(Request $request, AudienceMembre $membre): RedirectResponse
    {
        abort_unless((bool) $request->user()->planActif()?->audience, 403, "Cette fonctionnalité nécessite le plan Basique ou Pro.");

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

        app(AudienceService::class)->enregistrerInteraction(
            $boutique,
            AudienceInteraction::MESSAGE_RELANCE_ENVOYE,
            $conversation->produit_id ? Produit::withoutGlobalScopes()->find($conversation->produit_id) : null,
            $membre->user_id ? ['user_id' => $membre->user_id] : ['visiteur_token' => $membre->visiteur_token],
        );

        return back()->with('flash_success', 'Message envoyé.');
    }

    public function updateStatut(Request $request, AudienceMembre $membre): RedirectResponse
    {
        abort_unless((bool) $request->user()->planActif()?->audience, 403, "Cette fonctionnalité nécessite le plan Basique ou Pro.");

        $data = $request->validate(['statut' => ['required', 'string', Rule::in(self::STATUTS)]]);

        $membre->update($data);

        return back()->with('flash_success', 'Statut mis à jour.');
    }

    private function formaterMembre(AudienceMembre $membre): array
    {
        return [
            'id' => $membre->id,
            'nom' => $membre->nomAffiche(),
            'est_anonyme' => $membre->estAnonyme(),
            'statut' => $membre->statut,
            'total_interactions' => $membre->interactions_count,
            'total_produits' => $membre->produits_distincts_count,
            'dernier_produit' => $membre->derniereInteraction?->produit?->nom,
            'derniere_interaction_type' => $membre->derniereInteraction?->type,
            'derniere_interaction_a' => $membre->derniereInteraction?->created_at?->diffForHumans(),
        ];
    }

    /**
     * Données réelles calculées depuis audience_membres/audience_interactions --
     * jamais de valeurs fictives, même pour l'aperçu verrouillé du plan Gratuit.
     */
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
