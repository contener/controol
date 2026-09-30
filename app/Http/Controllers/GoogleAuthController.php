<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NouvelUtilisateurService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Connexion / inscription via Google (OAuth) -- point d'entrée alternatif à
 * CreateNewUser (formulaire email/mot de passe). Réutilise NouvelUtilisateurService
 * pour que les effets de bord de l'inscription (essai gratuit, capture parrainage/
 * invitation boutique en session) restent identiques quel que soit le flux emprunté.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(NouvelUtilisateurService $nouvelUtilisateurService): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login')->with('flash_error', 'La connexion avec Google a échoué. Veuillez réessayer.');
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        // Compte existant créé au départ par email/mot de passe, avec la même adresse
        // que celle renvoyée par Google -- on le relie plutôt que d'en créer un second.
        // Google garantit une adresse déjà vérifiée pour tout compte Google actif ; on
        // s'en assure quand même explicitement avant de faire confiance à ce rapprochement.
        if (! $user && ($googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? true)) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->forceFill(['google_id' => $googleUser->getId()])->save();
            }
        }

        if (! $user) {
            $user = DB::transaction(function () use ($googleUser, $nouvelUtilisateurService) {
                return tap(User::forceCreate([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: explode('@', $googleUser->getEmail())[0],
                    'email' => $googleUser->getEmail(),
                    // Jamais utilisé pour se connecter (l'accès se fait uniquement via
                    // Google) -- l'utilisateur peut ensuite en définir un vrai via "Mot de
                    // passe oublié" s'il souhaite aussi pouvoir se connecter sans Google.
                    'password' => Hash::make(Str::random(40)),
                    'google_id' => $googleUser->getId(),
                ]), fn (User $nouveau) => $nouvelUtilisateurService->initialiser($nouveau));
            });
        }

        if (! $user->est_actif) {
            return redirect()->route('login')->with('flash_error', 'Ce compte a été désactivé. Contactez le support si vous pensez qu\'il s\'agit d\'une erreur.');
        }

        // Un compte protégé par 2FA ne doit jamais contourner le défi -- même
        // mécanisme que Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable,
        // réutilisé ici plutôt que dupliqué.
        if ($user->two_factor_secret && ! is_null($user->two_factor_confirmed_at)) {
            request()->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::guard('web')->login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
