<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WhatsappConnectorWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentification par jeton (app mobile Flutter) -- voir AuthController, même
// vérification d'identifiants que FortifyServiceProvider::authenticateUsing()
// (web), même limiteur de débit ("login", 5/minute par e-mail+IP).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Appelé uniquement par le service indépendant whatsapp-connector/ (Node.js) — authentifié
// par secret partagé (voir WhatsappConnectorWebhookController), jamais par un utilisateur.
Route::post('/webhooks/whatsapp-connector', [WhatsappConnectorWebhookController::class, 'handle']);
