<?php

use App\Http\Controllers\Api\WhatsappConnectorWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Appelé uniquement par le service indépendant whatsapp-connector/ (Node.js) — authentifié
// par secret partagé (voir WhatsappConnectorWebhookController), jamais par un utilisateur.
Route::post('/webhooks/whatsapp-connector', [WhatsappConnectorWebhookController::class, 'handle']);
