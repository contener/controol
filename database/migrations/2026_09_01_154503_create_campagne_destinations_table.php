<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagne_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campagne_id')->constrained('campagnes_sociales')->cascadeOnDelete();
            $table->foreignId('destination_sociale_id')->constrained('destinations_sociales')->cascadeOnDelete();
            $table->enum('statut', ['en_attente', 'en_cours', 'envoye', 'echec', 'non_autorise'])->default('en_attente');
            $table->timestamp('traite_at')->nullable();
            $table->timestamps();

            $table->index('campagne_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagne_destinations');
    }
};
