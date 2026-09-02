<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_naissance')->nullable()->after('name');
            $table->string('ville')->nullable()->after('date_naissance');
            $table->string('telephone')->nullable()->after('ville');
            $table->string('whatsapp')->nullable()->after('telephone');
            // Rôle système : 'user' (défaut), 'admin', 'super_admin'.
            // Volontairement absent de $fillable sur le modèle User — un changement de
            // rôle ne peut jamais transiter par une requête utilisateur (formulaire de
            // profil, API...), uniquement via la commande artisan dédiée ou forceFill().
            $table->string('role', 20)->default('user')->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['date_naissance', 'ville', 'telephone', 'whatsapp', 'role']);
        });
    }
};
