<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Lien vers la page de paiement hébergée (ex. MoneyFusion) associée à ce plan.
            // Le paiement reste vérifié manuellement par un Super Admin avant activation
            // (PaiementValidationService) — ce lien ne fait qu'orienter l'utilisateur vers
            // la page de paiement, il n'active jamais l'abonnement automatiquement.
            $table->string('lien_paiement')->nullable()->after('prix');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('lien_paiement');
        });
    }
};
