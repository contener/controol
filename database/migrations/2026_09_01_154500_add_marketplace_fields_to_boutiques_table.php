<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            $table->string('pays')->nullable()->after('ville');
            // Choix du propriétaire (soumis à l'éligibilité de son abonnement).
            $table->boolean('marketplace_visible')->default(false)->after('statut');
            // Verrou du Super Administrateur : prioritaire sur tout le reste, y compris
            // un abonnement actif et marketplace_visible=true (cf. Boutique::estEligibleMarketplace).
            $table->boolean('marketplace_disabled_by_admin')->default(false)->after('marketplace_visible');

            $table->index(['statut', 'marketplace_visible', 'marketplace_disabled_by_admin'], 'boutiques_marketplace_index');
        });
    }

    public function down(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            $table->dropIndex('boutiques_marketplace_index');
            $table->dropColumn(['pays', 'marketplace_visible', 'marketplace_disabled_by_admin']);
        });
    }
};
