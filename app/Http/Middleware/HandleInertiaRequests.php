<?php

namespace App\Http\Middleware;

use App\Models\Conversation;
use App\Models\NotificationUtilisateur;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('flash_success'),
                'error' => fn () => $request->session()->get('flash_error'),
                'lien_conversation' => fn () => $request->session()->get('flash_lien_conversation'),
            ],
            'google' => [
                // Le bouton "Continuer avec Google" (Login/Register) reste masqué tant que
                // GOOGLE_CLIENT_ID n'est pas configuré, plutôt que d'afficher un bouton qui
                // échouerait systématiquement.
                'active' => fn () => filled(config('services.google.client_id')),
            ],
            'mesBoutiques' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return [];
                }

                // Fusionne les boutiques possédées et celles où l'utilisateur est
                // collaborateur (voir Boutique::collaborateurs()) -- sinon le
                // sélecteur de boutique d'AppLayout.vue resterait vide pour un
                // collaborateur malgré un accès réel. est_proprietaire distingue les
                // deux côté Vue (badge, et visibilité du lien de gestion de l'équipe).
                $possedees = $user->boutiques()->get(['id', 'nom', 'slug', 'statut'])
                    ->map(fn ($b) => [...$b->only(['id', 'nom', 'slug', 'statut']), 'est_proprietaire' => true]);

                $collaborees = $user->boutiquesCollaborateur()->get(['boutiques.id', 'boutiques.nom', 'boutiques.slug', 'boutiques.statut'])
                    ->map(fn ($b) => [...$b->only(['id', 'nom', 'slug', 'statut']), 'est_proprietaire' => false]);

                return $possedees->concat($collaborees)->sortBy('nom')->values();
            },
            'messagesNonLus' => function () use ($request) {
                $user = $request->user();

                if (! $user || ! $user->currentBoutique) {
                    return 0;
                }

                // Nombre de CONVERSATIONS avec au moins un message non lu, pas de messages
                // bruts — scope automatique via BelongsToBoutique (Auth::check() est vrai ici).
                return Conversation::where('messages_non_lus_boutique', '>', 0)->count();
            },
            'mesConversationsNonLues' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return 0;
                }

                // Vue "acheteur" : jamais scopée par currentBoutique, donc withoutGlobalScopes.
                return Conversation::withoutGlobalScopes()
                    ->where('visiteur_user_id', $user->id)
                    ->where('messages_non_lus_visiteur', '>', 0)
                    ->count();
            },
            'notificationsNonLuesCount' => function () use ($request) {
                $user = $request->user();

                if (! $user) {
                    return 0;
                }

                return NotificationUtilisateur::where('user_id', $user->id)->whereNull('lu_a')->count();
            },
        ];
    }
}
