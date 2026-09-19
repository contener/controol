<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suivis_boutique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('notifications_actives')->default(true);
            $table->timestamp('abonne_a')->nullable();
            // "Actif" = desabonne_a === null (pas de colonne statut séparée, même
            // logique que EssaiUtilisateur::statut() : une deuxième source de vérité
            // qui doit rester synchronisée avec un timestamp finit par se désynchroniser).
            $table->timestamp('desabonne_a')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'boutique_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suivis_boutique');
    }
};
