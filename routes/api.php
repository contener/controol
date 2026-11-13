<?php

use App\Http\Controllers\Api\AbonnementController;
use App\Http\Controllers\Api\AudienceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoutiqueController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepenseController;
use App\Http\Controllers\Api\EquipeController;
use App\Http\Controllers\Api\FactureController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MesConversationsController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ParrainageController;
use App\Http\Controllers\Api\ProduitController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\WhatsappConnectorWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont nommées "api.*" -- Route::apiResource()
// génère sinon les mêmes noms (produits.store, clients.index, ...) que les
// Route::resource() équivalents de web.php, et écrase silencieusement ces noms
// dans la table de routage (route('produits.store') se mettrait à résoudre vers
// l'API au lieu du web, cassant les redirections du site -- bug réel constaté
// au déploiement du 2026-11-13, voir controol.md §5.21).
Route::name('api.')->group(function () {
    // Authentification par jeton (app mobile Flutter) -- voir AuthController, même
    // vérification d'identifiants que FortifyServiceProvider::authenticateUsing()
    // (web), même limiteur de débit ("login", 5/minute par e-mail+IP).
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register']);

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

        // Compte (coquille mobile, profil, préférences, vue globale) -- voir controol.md §5.21.
        Route::prefix('compte')->group(function () {
            Route::get('/resume', [CompteController::class, 'resume']);
            Route::get('/vue-globale', [CompteController::class, 'vueGlobale']);
            Route::post('/profil', [CompteController::class, 'updateProfil']);
            Route::delete('/profil/photo', [CompteController::class, 'supprimerPhoto']);
            Route::put('/mot-de-passe', [CompteController::class, 'updateMotDePasse']);
            Route::patch('/preferences', [CompteController::class, 'updatePreferences']);
        });

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/tout-lu', [NotificationController::class, 'toutMarquerLu']);
        Route::patch('/notifications/{notification}/lu', [NotificationController::class, 'marquerLu']);

        // "gestion" avant {boutique} : sinon interprété comme un id de boutique.
        Route::get('/boutiques', [BoutiqueController::class, 'index']);
        Route::get('/boutiques/gestion', [BoutiqueController::class, 'gestion']);
        Route::post('/boutiques', [BoutiqueController::class, 'store']);
        Route::get('/boutiques/{boutique}', [BoutiqueController::class, 'show']);
        Route::post('/boutiques/{boutique}', [BoutiqueController::class, 'update']);
        Route::delete('/boutiques/{boutique}', [BoutiqueController::class, 'destroy']);
        Route::post('/boutiques/{boutique}/switch', [BoutiqueController::class, 'switch']);
        Route::patch('/boutiques/{boutique}/marketplace', [BoutiqueController::class, 'updateMarketplace']);

        Route::get('/dashboard', [DashboardController::class, 'index']);

        Route::apiResource('clients', ClientController::class);
        Route::patch('/produits/{produit}/marketplace', [ProduitController::class, 'updateMarketplace']);
        Route::apiResource('produits', ProduitController::class);
        Route::apiResource('depenses', DepenseController::class);

        // Avant apiResource('factures') : sinon "contexte-creation" serait interprété
        // comme un {facture} à résoudre par liaison de modèle (404 avant d'atteindre
        // la bonne méthode).
        Route::get('/factures/contexte-creation', [FactureController::class, 'contexteCreation']);
        Route::patch('/factures/{facture}/statut', [FactureController::class, 'updateStatut']);
        Route::get('/factures/{facture}/pdf', [FactureController::class, 'pdf']);
        Route::post('/factures/{facture}/dupliquer', [FactureController::class, 'dupliquer']);
        Route::apiResource('factures', FactureController::class);

        Route::get('/stock', [StockController::class, 'index']);
        Route::get('/stock/{produit}/mouvements', [StockController::class, 'mouvements']);
        Route::post('/stock', [StockController::class, 'store']);

        Route::prefix('marketplace')->group(function () {
            Route::get('/', [MarketplaceController::class, 'index']);
            Route::get('/boutiques/{slug}', [MarketplaceController::class, 'boutique']);
            Route::post('/boutiques/{slug}/suivre', [MarketplaceController::class, 'suivre']);
            Route::delete('/boutiques/{slug}/suivre', [MarketplaceController::class, 'neplusSuivre']);
            Route::post('/boutiques/{slug}/produits/{produit}/jaime', [MarketplaceController::class, 'aimer'])->middleware('throttle:20,1');
            Route::get('/boutiques/{slug}/conversation', [MarketplaceController::class, 'conversation']);
            Route::post('/boutiques/{slug}/messages', [MarketplaceController::class, 'envoyerMessage'])->middleware('throttle:5,1');
        });

        Route::prefix('audience')->group(function () {
            Route::get('/', [AudienceController::class, 'index']);
            Route::get('/{membre}', [AudienceController::class, 'show']);
            Route::post('/{membre}/whatsapp', [AudienceController::class, 'relancerWhatsapp']);
            Route::post('/{membre}/message', [AudienceController::class, 'relancerMessage']);
            Route::patch('/{membre}/statut', [AudienceController::class, 'updateStatut']);
        });

        Route::prefix('equipe')->group(function () {
            Route::get('/', [EquipeController::class, 'index']);
            Route::post('/', [EquipeController::class, 'store']);
            Route::patch('/{collaborateur}', [EquipeController::class, 'update']);
            Route::delete('/{collaborateur}', [EquipeController::class, 'destroy']);
        });

        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store']);
        Route::get('/messages/{conversation}', [MessageController::class, 'show']);
        Route::post('/messages/{conversation}/repondre', [MessageController::class, 'repondre']);
        Route::patch('/messages/{conversation}/statut', [MessageController::class, 'updateStatut']);

        Route::get('/mes-conversations', [MesConversationsController::class, 'index']);
        Route::get('/mes-conversations/{conversation}', [MesConversationsController::class, 'show']);
        Route::post('/mes-conversations/{conversation}/repondre', [MesConversationsController::class, 'repondre']);

        Route::get('/abonnement', [AbonnementController::class, 'index']);
        Route::post('/abonnement/plans/{plan}', [AbonnementController::class, 'changer']);

        Route::get('/parrainage', [ParrainageController::class, 'index']);
    });
});
