<?php

use App\Http\Controllers\Admin\MarketplaceController as AdminMarketplaceController;
use App\Http\Controllers\Admin\PaiementController as AdminPaiementController;
use App\Http\Controllers\AbonnementController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\CampagneSocialeController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompteDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\DestinationSocialeController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FactureModeleController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\PublicBoutiqueController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/boutique/{slug}', [PublicBoutiqueController::class, 'show'])->name('public.boutique');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/compte/dashboard', CompteDashboardController::class)->name('compte.dashboard');
    Route::patch('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    Route::resource('boutiques', BoutiqueController::class)->except(['show']);
    Route::post('/boutiques/{boutique}/switch', [BoutiqueController::class, 'switch'])->name('boutiques.switch');
    Route::patch('/boutiques/{boutique}/marketplace', [BoutiqueController::class, 'updateMarketplace'])->name('boutiques.marketplace');

    Route::middleware('boutique.selected')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::resource('clients', ClientController::class)->except(['show']);
        Route::resource('produits', ProduitController::class)->except(['show']);
        Route::resource('depenses', DepenseController::class)->except(['show']);

        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/{produit}/mouvements', [StockController::class, 'mouvements'])->name('stock.mouvements');
        Route::post('/stock/mouvements', [StockController::class, 'store'])->name('stock.store');

        Route::get('/factures/modeles', [FactureModeleController::class, 'index'])->name('factures.modeles');
        Route::resource('factures', FactureController::class);
        Route::patch('/factures/{facture}/statut', [FactureController::class, 'updateStatut'])->name('factures.statut');
        Route::get('/factures/{facture}/pdf', [FactureController::class, 'downloadPdf'])->name('factures.pdf');
        Route::post('/factures/{facture}/dupliquer', [FactureController::class, 'duplicate'])->name('factures.dupliquer');

        Route::get('/partage-social', [DestinationSocialeController::class, 'index'])->name('partage-social.index');
        Route::resource('destinations-sociales', DestinationSocialeController::class)->except(['index', 'show', 'create', 'edit']);

        Route::post('/campagnes-sociales', [CampagneSocialeController::class, 'demarrer'])->name('campagnes-sociales.demarrer');
        Route::post('/campagnes-sociales/{campagneSociale}/arreter', [CampagneSocialeController::class, 'arreter'])->name('campagnes-sociales.arreter');
        Route::post('/campagne-destinations/{campagneDestination}/confirmer', [CampagneSocialeController::class, 'confirmerDestination'])->name('campagne-destinations.confirmer');
    });

    Route::get('/abonnement', [AbonnementController::class, 'index'])->name('abonnement.index');
    Route::post('/abonnement/plans/{plan}', [AbonnementController::class, 'demanderChangement'])->name('abonnement.changer');

    // Toute route sous /admin exige le rôle super_admin, vérifié côté serveur à chaque
    // requête par le middleware EnsureSuperAdmin — jamais uniquement par l'UI.
    Route::prefix('admin')->name('admin.')->middleware('super_admin')->group(function () {
        Route::get('/paiements', [AdminPaiementController::class, 'index'])->name('paiements.index');
        Route::get('/paiements/{paiement}', [AdminPaiementController::class, 'show'])->name('paiements.show');
        Route::post('/paiements/{paiement}/approuver', [AdminPaiementController::class, 'approuver'])->name('paiements.approuver');
        Route::post('/paiements/{paiement}/rejeter', [AdminPaiementController::class, 'rejeter'])->name('paiements.rejeter');

        Route::get('/marketplace', [AdminMarketplaceController::class, 'index'])->name('marketplace.index');
        Route::patch('/marketplace/{boutique}/basculer', [AdminMarketplaceController::class, 'basculer'])->name('marketplace.basculer');
    });
});
