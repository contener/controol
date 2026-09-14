<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_agents', function (Blueprint $table) {
            // deconnecte | connexion | qr | connecte
            $table->string('whatsapp_statut')->default('deconnecte')->after('actif');
            $table->string('whatsapp_numero')->nullable()->after('whatsapp_statut');
            $table->timestamp('whatsapp_connecte_a')->nullable()->after('whatsapp_numero');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_agents', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_statut', 'whatsapp_numero', 'whatsapp_connecte_a']);
        });
    }
};
