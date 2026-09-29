<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Lien de paiement distinct utilisé uniquement pendant un essai gratuit en
            // cours (prix promotionnel, ex. 3500 FCFA au lieu de 5000) -- le fournisseur
            // de paiement hébergé a un montant fixe par lien, donc un seul lien_paiement
            // ne peut pas servir les deux prix du plan Basique. Repli sur lien_paiement
            // si absent (voir AbonnementController::demanderChangement()).
            $table->string('lien_paiement_promo')->nullable()->after('lien_paiement');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('lien_paiement_promo');
        });
    }
};
