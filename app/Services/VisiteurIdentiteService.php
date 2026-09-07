<?php

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Identifie un visiteur anonyme sans jamais créer de faux compte : un jeton UUID posé dans
 * un cookie (déjà chiffré/inviolable via le middleware EncryptCookies par défaut de Laravel)
 * pour reconnaître le même navigateur automatiquement, complété par un lien signé comme
 * filet de secours pour un accès depuis un autre appareil.
 */
class VisiteurIdentiteService
{
    public const COOKIE_TOKEN = 'ctrl_visiteur_token';

    public function resoudreOuCreerJeton(Request $request): string
    {
        $existant = $request->cookie(self::COOKIE_TOKEN);
        if ($existant && Str::isUuid($existant)) {
            return $existant;
        }

        $jeton = (string) Str::uuid();
        Cookie::queue(Cookie::forever(self::COOKIE_TOKEN, $jeton));

        return $jeton;
    }

    /**
     * Fait correspondre le navigateur courant au jeton de la conversation (ouverture d'un
     * lien signé, ou conversation lancée par la boutique pour un Client) — n'agit jamais sur
     * une conversation identifiée par un compte utilisateur.
     */
    public function synchroniserCookie(Request $request, Conversation $conversation): void
    {
        if ($conversation->visiteur_user_id !== null) {
            return;
        }

        if (! $conversation->visiteur_token) {
            $conversation->update(['visiteur_token' => (string) Str::uuid()]);
        }

        Cookie::queue(Cookie::forever(self::COOKIE_TOKEN, $conversation->visiteur_token));
    }

    public function aAcces(Request $request, Conversation $conversation): bool
    {
        if ($conversation->visiteur_user_id !== null) {
            return Auth::check() && Auth::id() === $conversation->visiteur_user_id;
        }

        $jeton = $request->cookie(self::COOKIE_TOKEN);

        return $jeton && $conversation->visiteur_token && hash_equals((string) $conversation->visiteur_token, (string) $jeton);
    }

    public function verifierAcces(Request $request, Conversation $conversation): void
    {
        abort_unless($this->aAcces($request, $conversation), 403);
    }

    public function lienSigne(Conversation $conversation): string
    {
        return URL::signedRoute('public.conversations.show', ['conversation' => $conversation->id], now()->addDays(90));
    }
}
