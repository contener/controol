<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Ouvert à de futurs types (ex. 'campagne') — un seul type existe en phase 1.
            $table->string('type')->default('essai_rappel');
            $table->string('titre');
            // Déjà interpolé (variables remplacées) à la création — jamais re-rendu à
            // la lecture, pas de dépendance à un modèle qui pourrait changer entre-temps.
            $table->text('message');
            $table->boolean('est_promotionnelle')->default(false);
            $table->timestamp('lu_a')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'lu_a']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_utilisateurs');
    }
};
