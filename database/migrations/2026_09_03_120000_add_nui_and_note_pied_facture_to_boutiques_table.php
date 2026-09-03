<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            // Numéro d'Identifiant Unique (identifiant fiscal/commercial, ex. Cameroun).
            $table->string('nui')->nullable()->after('taux_tva_defaut');
            // Note fixe affichée en bas de chaque facture de cette boutique (ex. conditions
            // de garantie) — distincte du champ "notes" propre à chaque facture.
            $table->text('note_pied_facture')->nullable()->after('nui');
        });
    }

    public function down(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            $table->dropColumn(['nui', 'note_pied_facture']);
        });
    }
};
