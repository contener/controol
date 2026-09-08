<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdministrateurController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ContactExportController;
use App\Http\Controllers\Admin\ContactImportController;
use App\Http\Controllers\Admin\MarketplaceController as AdminMarketplaceController;
use App\Http\Controllers\Admin\PaiementController as AdminPaiementController;
use App\Http\Controllers\Admin\UtilisateurController as AdminUtilisateurController;
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
use App\Http\Controllers\GuestConversationController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MesConversationsController;
use App\Http\Controllers\MessageController;
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
Route::post('/boutique/{slug}/messages', [PublicBoutiqueController::class, 'envoyerMessage'])
    ->middleware('throttle:5,1')
    ->name('public.boutique.messages.store');

Route::get('/conversations/{conversation}', [GuestConversationController::class, 'show'])
    ->middleware('signed')
    ->name('public.conversations.show');
Route::post('/conversations/{conversation}/repondre', [GuestConversationController::class, 'repondre'])
    ->middleware('throttle:5,1')
    ->name('public.conversations.repondre');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'account.active',
])->group(function () {
    Route::get('/compte/dashboard', CompteDashboardController::class)->name('compte.dashboard');
    Route::patch('/preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    Route::resource('boutiques', BoutiqueController::class)->except(['show']);
    Route::post('/boutiques/{boutique}/switch', [BoutiqueController::class, 'switch'])->name('boutiques.switch');
    Route::patch('/boutiques/{boutique}/marketplace', [BoutiqueController::class, 'updateMarketplace'])->name('boutiques.marketplace');
    Route::patch('/boutiques/{boutique}/nui', [BoutiqueController::class, 'updateNui'])->name('boutiques.nui');

    // Hors boutique.selected : un utilisateur peut consulter ses conversations en tant que
    // visiteur/acheteur même s'il ne possède lui-même aucune boutique.
    Route::get('/mes-conversations', [MesConversationsController::class, 'index'])->name('mes-conversations.index');
    Route::post('/mes-conversations/{conversation}/repondre', [MesConversationsController::class, 'repondre'])->name('mes-conversations.repondre');

    Route::middleware('boutique.selected')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::resource('clients', ClientController::class)->except(['show']);
        Route::resource('produits', ProduitController::class)->except(['show']);
        Route::resource('depenses', DepenseController::class)->except(['show']);

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('/messages/{conversation}/repondre', [MessageController::class, 'repondre'])->name('messages.repondre');
        Route::patch('/messages/{conversation}/statut', [MessageController::class, 'updateStatut'])->name('messages.statut');

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

    // Toute route sous /admin exige au minimum d'être admin ou super_admin ET actif,
    // vérifié côté serveur par EnsureAdminAccess — jamais uniquement par l'UI. Chaque
    // action sensible ajoute en plus sa propre permission granulaire (admin.permission),
    // vérifiée par EnsureAdminPermission via User::hasAdminPermission() (qui court-circuite
    // toujours sur super_admin, donc aucune régression pour le Super Admin existant).
    Route::prefix('admin')->name('admin.')->middleware('admin.access')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('/paiements', [AdminPaiementController::class, 'index'])->name('paiements.index')->middleware('admin.permission:paiements.voir');
        Route::get('/paiements/{paiement}', [AdminPaiementController::class, 'show'])->name('paiements.show')->middleware('admin.permission:paiements.voir');
        Route::post('/paiements/{paiement}/approuver', [AdminPaiementController::class, 'approuver'])->name('paiements.approuver')->middleware('admin.permission:paiements.valider');
        Route::post('/paiements/{paiement}/rejeter', [AdminPaiementController::class, 'rejeter'])->name('paiements.rejeter')->middleware('admin.permission:paiements.refuser');

        Route::get('/marketplace', [AdminMarketplaceController::class, 'index'])->name('marketplace.index')->middleware('admin.permission:marketplace.voir');
        Route::patch('/marketplace/{boutique}/basculer', [AdminMarketplaceController::class, 'basculer'])->name('marketplace.basculer')->middleware('admin.permission:marketplace.suspendre');

        // Gérer les administrateurs eux-mêmes reste un privilège non-délégable : aucune
        // permission granulaire n'y donne accès, uniquement le rôle super_admin exact
        // (EnsureSuperAdmin, inchangé) — voir App\Support\AdminPermissions pour le
        // raisonnement complet.
        Route::prefix('administrateurs')->name('administrateurs.')->middleware('super_admin')->group(function () {
            Route::get('/', [AdministrateurController::class, 'index'])->name('index');
            Route::get('/creer', [AdministrateurController::class, 'create'])->name('create');
            Route::post('/', [AdministrateurController::class, 'store'])->name('store');
            Route::get('/{administrateur}/modifier', [AdministrateurController::class, 'edit'])->name('edit');
            Route::put('/{administrateur}', [AdministrateurController::class, 'update'])->name('update');
            Route::patch('/{administrateur}/basculer', [AdministrateurController::class, 'basculerActivation'])->name('basculer');
        });

        // Utilisateurs finaux (boutiquiers) — distinct des administrateurs ci-dessus.
        // Chaque action a sa propre permission granulaire ; contrairement à la gestion
        // des administrateurs, ce n'est volontairement PAS exclusif à super_admin, pour
        // rester cohérent avec le catalogue de permissions existant (un Super Admin peut
        // déléguer utilisateurs.suspendre/supprimer à un administrateur de confiance).
        Route::prefix('utilisateurs')->name('utilisateurs.')->group(function () {
            Route::get('/', [AdminUtilisateurController::class, 'index'])->name('index')->middleware('admin.permission:utilisateurs.voir');
            Route::get('/{utilisateur}', [AdminUtilisateurController::class, 'show'])->name('show')->middleware('admin.permission:utilisateurs.voir');
            Route::patch('/{utilisateur}/basculer', [AdminUtilisateurController::class, 'basculerActivation'])->name('basculer')->middleware('admin.permission:utilisateurs.suspendre');
            Route::delete('/{utilisateur}/boutiques/{boutique}', [AdminUtilisateurController::class, 'destroyBoutique'])->name('boutiques.destroy')->middleware('admin.permission:utilisateurs.supprimer');
            Route::delete('/{utilisateur}', [AdminUtilisateurController::class, 'destroy'])->name('destroy')->middleware('admin.permission:utilisateurs.supprimer');

            Route::post('/{utilisateur}/whatsapp', [AdminUtilisateurController::class, 'whatsappContacter'])->name('whatsapp.contacter')->middleware('admin.permission:whatsapp.contacter');
            Route::patch('/whatsapp-logs/{log}/confirmer', [AdminUtilisateurController::class, 'whatsappConfirmer'])->name('whatsapp.confirmer')->middleware('admin.permission:whatsapp.contacter');
        });

        // Base de contacts de prospection — distincte des utilisateurs CONTROOL ci-dessus,
        // rapprochée automatiquement par numéro (voir App\Observers\UserObserver).
        Route::prefix('contacts')->name('contacts.')->group(function () {
            Route::get('/', [ContactController::class, 'index'])->name('index')->middleware('admin.permission:contacts.voir');
            Route::post('/', [ContactController::class, 'store'])->name('store')->middleware('admin.permission:contacts.modifier');
            Route::get('/{contact}', [ContactController::class, 'show'])->name('show')->middleware('admin.permission:contacts.voir');
            Route::put('/{contact}', [ContactController::class, 'update'])->name('update')->middleware('admin.permission:contacts.modifier');
            Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy')->middleware('admin.permission:contacts.supprimer');
            Route::patch('/{contact}/statut-commercial', [ContactController::class, 'updateStatutCommercial'])->name('statut-commercial')->middleware('admin.permission:contacts.modifier');
            Route::patch('/statut-commercial-groupe', [ContactController::class, 'statutCommercialGroupe'])->name('statut-commercial-groupe')->middleware('admin.permission:contacts.modifier');
            Route::patch('/{contact}/statut-whatsapp', [ContactController::class, 'updateStatutWhatsapp'])->name('statut-whatsapp')->middleware('admin.permission:contacts.modifier');
            Route::post('/{contact}/whatsapp', [ContactController::class, 'whatsappContacter'])->name('whatsapp.contacter')->middleware('admin.permission:contacts.whatsapp_contacter');

            Route::get('/import/historique', [ContactImportController::class, 'index'])->name('import.index')->middleware('admin.permission:contacts.importer');
            Route::post('/import/analyser', [ContactImportController::class, 'analyser'])->name('import.analyser')->middleware('admin.permission:contacts.importer');
            Route::post('/import/{import}/confirmer', [ContactImportController::class, 'confirmer'])->name('import.confirmer')->middleware('admin.permission:contacts.importer');
            Route::get('/import/{import}', [ContactImportController::class, 'show'])->name('import.show')->middleware('admin.permission:contacts.importer');

            Route::get('/export/fichier', [ContactExportController::class, 'export'])->name('export')->middleware('admin.permission:contacts.exporter');
        });
    });
});
