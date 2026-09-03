<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            // Texte libre de garantie propre à CETTE facture (durée + exclusions), saisi
            // dans le formulaire de facture — distinct de boutiques.note_pied_facture qui
            // est une note fixe appliquée à toutes les factures de la boutique.
            $table->text('garantie')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('garantie');
        });
    }
};
