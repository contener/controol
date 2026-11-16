<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_utilisateurs', function (Blueprint $table) {
            // Références de l'objet concerné (boutique_id, conversation_id, produit_id,
            // slug...) : l'application mobile ouvre directement le bon écran, en
            // basculant au besoin sur la bonne boutique, sans deviner depuis le lien web.
            $table->json('donnees')->nullable()->after('lien');
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications_utilisateurs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type']);
            $table->dropColumn('donnees');
        });
    }
};
