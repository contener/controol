<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->string('type', 20)->default('facture')->after('client_id');
        });

        Schema::table('compteurs_facture', function (Blueprint $table) {
            // Séquence de numérotation distincte par type : un proforma ne doit jamais
            // consommer un numéro de la séquence légale des factures.
            $table->string('type', 20)->default('facture')->after('annee');
        });

        Schema::table('compteurs_facture', function (Blueprint $table) {
            $table->dropUnique(['boutique_id', 'annee']);
            $table->unique(['boutique_id', 'annee', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('compteurs_facture', function (Blueprint $table) {
            $table->dropUnique(['boutique_id', 'annee', 'type']);
        });

        Schema::table('compteurs_facture', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->unique(['boutique_id', 'annee']);
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
