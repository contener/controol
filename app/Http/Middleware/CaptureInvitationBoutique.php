<?php

namespace App\Http\Middleware;

use App\Models\Boutique;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture ?boutique=slug sur les pages d'inscription et de création de boutique, et le
 * place en session. C'est le seul moyen de faire traverser cette info jusqu'à
 * CreateNewUser::create() (route Fortify, formulaire Jetstream non modifiable) et
 * BoutiqueController::store(), malgré la redirection intermédiaire
 * (EnsureBoutiqueSelected) qui ne préserve pas la query string.
 *
 * Peu importe le bouton/lien cliqué sur la page boutique publique (popup, bandeau du
 * bas...) : dès lors que ?boutique=slug est présent, la création de compte/boutique qui
 * en résulte compte comme un abonnement à cette boutique -- un visiteur qui partage un
 * lien de boutique s'attend à recevoir un abonné pour toute inscription qui en découle,
 * quel que soit le point d'entrée exact sur la page.
 */
class CaptureInvitationBoutique
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->routeIs(['register', 'boutiques.create']) && $request->filled('boutique')) {
            $boutique = Boutique::where('slug', $request->string('boutique')->toString())
                ->where('statut', 'active')
                ->first();

            if ($boutique) {
                $request->session()->put('invitation_boutique_slug', $boutique->slug);
            }
        }

        return $next($request);
    }
}
