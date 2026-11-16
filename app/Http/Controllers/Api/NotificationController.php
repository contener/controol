<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationUtilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Équivalent API de NotificationUtilisateurController (cloche de notifications,
 * NotificationBell.vue) -- mêmes 20 dernières, mêmes règles d'appartenance.
 */
class NotificationController extends Controller
{
    /**
     * ?categorie= (messages, jaime, abonnes, commandes, boutiques, abonnement, autres)
     * filtre par regroupement de types (NotificationUtilisateur::CATEGORIES) ;
     * ?page= pagine par 30. Sans paramètre : mêmes clés qu'avant (anciennes versions).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $categorie = $request->string('categorie')->toString();

        $requete = NotificationUtilisateur::where('user_id', $userId);

        if (isset(NotificationUtilisateur::CATEGORIES[$categorie])) {
            $requete->whereIn('type', NotificationUtilisateur::CATEGORIES[$categorie]);
        } elseif ($categorie === 'autres') {
            $requete->whereNotIn('type', array_merge(...array_values(NotificationUtilisateur::CATEGORIES)));
        }

        $page = $requete->latest('created_at')->latest('id')->paginate(30);

        $nonLuesParCategorie = NotificationUtilisateur::where('user_id', $userId)
            ->whereNull('lu_a')
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $compteurs = collect(NotificationUtilisateur::CATEGORIES)
            ->map(fn ($types) => (int) $nonLuesParCategorie->only($types)->sum())
            ->put('autres', (int) $nonLuesParCategorie->except(array_merge(...array_values(NotificationUtilisateur::CATEGORIES)))->sum());

        return response()->json([
            'notifications' => $page->items(),
            'page' => $page->currentPage(),
            'derniere_page' => $page->lastPage(),
            'non_lues' => (int) $nonLuesParCategorie->sum(),
            'non_lues_par_categorie' => $compteurs,
        ]);
    }

    public function marquerLu(Request $request, NotificationUtilisateur $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->marquerLu();

        return response()->json(['ok' => true]);
    }

    public function toutMarquerLu(Request $request): JsonResponse
    {
        NotificationUtilisateur::where('user_id', $request->user()->id)
            ->whereNull('lu_a')
            ->update(['lu_a' => now()]);

        return response()->json(['ok' => true]);
    }
}
