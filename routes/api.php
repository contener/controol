<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoutiqueController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\ProduitController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\WhatsappConnectorWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentification par jeton (app mobile Flutter) -- voir AuthController, même
// vérification d'identifiants que FortifyServiceProvider::authenticateUsing()
// (web), même limiteur de débit ("login", 5/minute par e-mail+IP).
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Appelé uniquement par le service indépendant whatsapp-connector/ (Node.js) — authentifié
// par secret partagé (voir WhatsappConnectorWebhookController), jamais par un utilisateur.
Route::post('/webhooks/whatsapp-connector', [WhatsappConnectorWebhookController::class, 'handle']);

// API métier de l'application mobile Flutter -- voir controol.md §5.21. Chaque
// contrôleur Api\* réutilise les mêmes FormRequests/Policies/Services que son
// équivalent web, jamais une seconde définition des règles métier.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/boutiques', [BoutiqueController::class, 'index']);
    Route::post('/boutiques/{boutique}/switch', [BoutiqueController::class, 'switch']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::apiResource('clients', ClientController::class);
    Route::apiResource('produits', ProduitController::class);
    Route::apiResource('depenses', DepenseController::class);

    // Avant apiResource('factures') : sinon "contexte-creation" serait interprété
    // comme un {facture} à résoudre par liaison de modèle (404 avant d'atteindre
    // la bonne méthode).
    Route::get('/factures/contexte-creation', [FactureController::class, 'contexteCreation']);
    Route::patch('/factures/{facture}/statut', [FactureController::class, 'updateStatut']);
    Route::apiResource('factures', FactureController::class);

    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/stock/{produit}/mouvements', [StockController::class, 'mouvements']);
    Route::post('/stock', [StockController::class, 'store']);
});
