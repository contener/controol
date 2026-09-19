<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table à une seule ligne (voir ParametreEssai::actuel()) — évite de construire
        // un système de paramètres génériques non demandé par le cahier des charges.
        Schema::create('parametres_essai', function (Blueprint $table) {
            $table->id();
            $table->time('heure_notification')->default('08:00:00');
            $table->string('fuseau')->default('Africa/Douala');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres_essai');
    }
};
