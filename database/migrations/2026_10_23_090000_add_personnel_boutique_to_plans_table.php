<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Déverrouille la possibilité, pour le propriétaire d'une boutique, de créer des
 * comptes collaborateurs et de leur donner accès à sa boutique -- même pattern que
 * marketplace/audience, jamais réutiliser un flag existant pour un nouveau besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('personnel_boutique')->default(false)->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('personnel_boutique');
        });
    }
};
