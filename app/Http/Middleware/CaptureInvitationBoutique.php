<?php

namespace App\Http\Middleware;

use App\Models\Boutique;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Capture ?boutique=slug (+ suivre=0|1) sur les pages d'inscription et de création de
 * boutique, et le place en session. C'est le seul moyen de faire traverser cette info
 * jusqu'à CreateNewUser::create() (route Fortify, formulaire Jetstream non modifiable)
 * et BoutiqueController::store(), malgré la redirection intermédiaire
 * (EnsureBoutiqueSelected) qui ne préserve pas la query string.
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
                $request->session()->put('invitation_boutique_suivre', $request->boolean('suivre'));
            }
        }

        return $next($request);
    }
}
