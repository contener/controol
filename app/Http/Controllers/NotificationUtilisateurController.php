<?php

namespace App\Http\Controllers;

use App\Models\NotificationUtilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationUtilisateurController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = NotificationUtilisateur::where('user_id', $request->user()->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return response()->json(['notifications' => $notifications]);
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
