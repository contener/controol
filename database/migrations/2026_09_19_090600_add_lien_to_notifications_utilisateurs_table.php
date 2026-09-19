<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_utilisateurs', function (Blueprint $table) {
            // Lien optionnel affiché sous forme de bouton ("Voir la boutique") --
            // déjà la bonne URL absolue construite côté serveur au moment de l'insertion
            // (ex. nouveau_produit), jamais reconstruite/devinée côté client.
            $table->string('lien')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('notifications_utilisateurs', function (Blueprint $table) {
            $table->dropColumn('lien');
        });
    }
};
