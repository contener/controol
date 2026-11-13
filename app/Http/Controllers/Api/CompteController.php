<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Api\Concerns\FournitEssaiActif;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Models\Conversation;
use App\Models\NotificationUtilisateur;
use App\Services\CompteStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Espace "Compte" de l'app mobile -- équivalent des données partagées par
 * HandleInertiaRequests::share() (boutiques accessibles + permissions, compteurs
 * de non-lus), de la Vue globale (CompteDashboardController) et de la page Profil
 * (Jetstream : informations, photo, mot de passe, préférences).
 */
class CompteController extends Controller
{
    use FournitEssaiActif, PasswordValidationRules;

    /**
     * Tout ce dont la coquille mobile a besoin pour afficher la navigation : quelles
     * sections montrer (mêmes permissions que AppLayout.vue), les pastilles de
     * non-lus, le plan actif et l'éventuel essai en cours.
     */
    public function resume(Request $request): JsonResponse
    {
        $user = $request->user();
        $boutique = $user->currentBoutique;

        $toutesPermissions = array_fill_keys(
            ['dashboard', 'clients', 'produits', 'factures', 'depenses', 'audience', 'messagerie', 'vue_globale'],
            true
        );

        $possedees = $user->boutiques()->get(['id', 'nom', 'slug', 'statut', 'devise', 'logo_path'])
            ->map(fn ($b) => [...$b->only(['id', 'nom', 'slug', 'statut', 'devise', 'logo_path']), 'est_proprietaire' => true, 'permissions' => $toutesPermissions]);

        $collaborees = $user->boutiquesCollaborateur()
            ->get(['boutiques.id', 'boutiques.nom', 'boutiques.slug', 'boutiques.statut', 'boutiques.devise', 'boutiques.logo_path'])
            ->map(fn ($b) => [
                ...$b->only(['id', 'nom', 'slug', 'statut', 'devise', 'logo_path']),
                'est_proprietaire' => false,
                'permissions' => collect($toutesPermissions)->map(fn ($v, $cle) => (bool) $b->pivot->{"acces_{$cle}"})->all(),
            ]);

        $plan = $user->planActif();

        return response()->json([
            'user' => $user,
            'boutiques' => $possedees->concat($collaborees)->sortBy('nom')->values(),
            'boutique_courante_id' => $boutique?->id,
            'plan_actif' => $plan?->only(['id', 'code', 'nom', 'marketplace', 'audience', 'personnel_boutique']),
            'essai_actif' => $this->essaiActif($user),
            'compteurs' => [
                'messages_non_lus' => $boutique ? Conversation::where('messages_non_lus_boutique', '>', 0)->count() : 0,
                'mes_conversations_non_lues' => Conversation::withoutGlobalScopes()
                    ->where('visiteur_user_id', $user->id)
                    ->where('messages_non_lus_visiteur', '>', 0)
                    ->count(),
                'notifications_non_lues' => NotificationUtilisateur::where('user_id', $user->id)->whereNull('lu_a')->count(),
            ],
        ]);
    }

    public function vueGlobale(Request $request, CompteStatsService $statsService): JsonResponse
    {
        $user = $request->user();

        abort_if(
            $user->currentBoutique && ! $user->currentBoutique->autoriseVueGlobale($user),
            403,
            "Vous n'avez pas accès à la vue globale. Demandez l'accès au propriétaire de la boutique."
        );

        return response()->json([
            'stats' => $statsService->pourCompte(
                $user,
                $request->string('debut')->toString() ?: null,
                $request->string('fin')->toString() ?: null,
            ),
        ]);
    }

    /**
     * Réutilise l'action Fortify UpdateUserProfileInformation telle quelle (mêmes
     * règles, même compression de la photo) -- multipart/form-data accepté pour la
     * photo de profil.
     */
    public function updateProfil(Request $request, UpdatesUserProfileInformation $updater): JsonResponse
    {
        $updater->update($request->user(), $request->all());

        return response()->json(['message' => 'Profil mis à jour.', 'user' => $request->user()->fresh()]);
    }

    public function supprimerPhoto(Request $request): JsonResponse
    {
        $request->user()->deleteProfilePhoto();

        return response()->json(['message' => 'Photo supprimée.', 'user' => $request->user()->fresh()]);
    }

    /**
     * Même règles de mot de passe que UpdateUserPassword (Fortify, web) via
     * PasswordValidationRules -- la règle 'current_password:web' de l'action web ne
     * peut pas servir ici (garde de session, pas de jeton), d'où la vérification
     * explicite par Hash::check().
     */
    public function updateMotDePasse(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => $this->passwordRules(),
        ]);

        $user = $request->user();

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->string('password')->toString())])->save();

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return response()->json(['message' => 'Préférences mises à jour.', 'user' => $request->user()->fresh()]);
    }
}
