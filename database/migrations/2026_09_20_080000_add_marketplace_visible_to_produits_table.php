<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            // Opt-in explicite : un produit/service n'apparaît plus automatiquement dans
            // la Marketplace dès sa création -- le propriétaire l'active lui-même depuis
            // la liste Produits & Services. N'affecte jamais le lien public direct de la
            // boutique (/boutique/{slug}), qui continue de montrer tous les produits actifs
            // (cf. Boutique::estEligibleMarketplace() : "Marketplace ≠ boutique publique").
            $table->boolean('marketplace_visible')->default(false)->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('marketplace_visible');
        });
    }
};
