<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // numero_fiscal (déjà existant) sert de NUI -- reste tel quel en base, la
            // confusion venait uniquement du libellé "Numéro fiscal / RCCM" qui mélangeait
            // les deux dans un seul champ. RCCM et code postal deviennent des champs
            // distincts, comme le NUI.
            $table->string('rccm')->nullable()->after('numero_fiscal');
            $table->string('code_postal')->nullable()->after('pays');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['rccm', 'code_postal']);
        });
    }
};
