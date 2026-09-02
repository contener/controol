<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            // Référence App\Enums\FactureModele (1 à 10) — pas de clé étrangère, ce sont
            // des designs figés livrés avec le code, pas des lignes de base de données.
            $table->unsignedTinyInteger('modele_id')->default(1)->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('modele_id');
        });
    }
};
