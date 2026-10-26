<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La "Vue globale" (Compte/Dashboard.vue, stats agrégées sur toutes les boutiques
 * possédées par le propriétaire) était visible par tous les collaborateurs, sans
 * distinction -- devient une 8e permission (voir Boutique::PERMISSIONS_COLLABORATEUR),
 * par défaut false comme les 7 autres. Contrairement à la migration d'origine de ce
 * système de permissions (2026_10_24_100000), aucun grandfathering ici : les
 * collaborateurs déjà créés perdent cet accès tant que le propriétaire ne le
 * recoche pas explicitement -- c'est la demande même de cette migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boutique_collaborateurs', function (Blueprint $table) {
            $table->boolean('acces_vue_globale')->default(false)->after('acces_messagerie');
        });
    }

    public function down(): void
    {
        Schema::table('boutique_collaborateurs', function (Blueprint $table) {
            $table->dropColumn('acces_vue_globale');
        });
    }
};
