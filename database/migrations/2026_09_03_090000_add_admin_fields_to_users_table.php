<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Étiquette d'affichage uniquement (ex. "ADMIN_PAIEMENTS") — jamais une source
            // d'autorisation, seules les lignes admin_permissions gouvernent les droits réels.
            $table->string('admin_role_label')->nullable()->after('role');

            // Générique (pas admin-only) : réutilisable plus tard pour suspendre un
            // utilisateur final sans nouvelle migration. Bloque l'accès à l'espace
            // d'administration quand false, ne bloque jamais la connexion normale à l'app.
            $table->boolean('est_actif')->default(true)->after('admin_role_label');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_role_label', 'est_actif']);
        });
    }
};
