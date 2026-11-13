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
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'notifications' => NotificationUtilisateur::where('user_id', $userId)->latest('created_at')->limit(20)->get(),
            'non_lues' => NotificationUtilisateur::where('user_id', $userId)->whereNull('lu_a')->count(),
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
