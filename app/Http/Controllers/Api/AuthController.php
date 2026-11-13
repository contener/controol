<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Authentification par jeton (Sanctum personal access token) pour les clients qui ne
 * sont pas le SPA web (Inertia, session/cookie) -- l'application mobile Flutter
 * (flutter_app/), en premier lieu. Réplique exactement la vérification de
 * FortifyServiceProvider::authenticateUsing() (email + mot de passe haché + compte
 * actif) plutôt que d'en réécrire une variante, pour ne jamais avoir deux definitions
 * divergentes de "identifiants valides" entre le web et l'API.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'nom_appareil' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ["Les identifiants fournis sont incorrects."],
            ]);
        }

        if (! $user->est_actif) {
            throw ValidationException::withMessages([
                'email' => ["Ce compte a été désactivé. Contactez le support si vous pensez qu'il s'agit d'une erreur."],
            ]);
        }

        // La confirmation 2FA (voir TwoFactorAuthenticatable) n'a pas encore son propre
        // flux de validation côté mobile (code à 6 chiffres) -- plutôt que de la
        // contourner silencieusement, on bloque explicitement avec un message clair.
        // Hors périmètre de l'initialisation du projet, à construire au besoin.
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'email' => ["La double authentification n'est pas encore prise en charge par l'application mobile. Connectez-vous depuis le site web, ou désactivez-la temporairement depuis votre profil."],
            ]);
        }

        return response()->json([
            'token' => $this->creerJeton($user, $request),
            'user' => $user,
        ]);
    }

    /**
     * Réutilise CreatesNewUsers (lié à CreateNewUser dans FortifyServiceProvider) --
     * mêmes règles de validation (voir PasswordValidationRules, "confirmed" inclus)
     * et mêmes effets de bord (NouvelUtilisateurService : essai/plan gratuit,
     * invitation boutique/parrainage en attente) que l'inscription web, jamais une
     * seconde définition. Connecte directement l'utilisateur (jeton) après
     * inscription, comme RegisteredUserController::store() le fait pour le web
     * (session) -- pas d'écran de vérification d'e-mail, cette fonctionnalité est
     * désactivée sur tout le projet (voir controol.md §2).
     */
    public function register(Request $request, CreatesNewUsers $createsNewUsers): JsonResponse
    {
        $user = $createsNewUsers->create($request->all());

        event(new Registered($user));

        return response()->json([
            'token' => $this->creerJeton($user, $request),
            'user' => $user,
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    private function creerJeton(User $user, Request $request): string
    {
        $nomAppareil = $request->string('nom_appareil')->toString() ?: 'Appareil mobile';

        return $user->createToken($nomAppareil)->plainTextToken;
    }
}
