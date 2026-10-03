<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Déverrouille la fonctionnalité Audience (visiteurs ayant interagi avec les produits
 * d'une boutique) pour les plans payants -- même pattern que marketplace/
 * modeles_facture_avances, jamais réutiliser un flag existant pour un nouveau besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('audience')->default(false)->after('modeles_facture_avances');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
