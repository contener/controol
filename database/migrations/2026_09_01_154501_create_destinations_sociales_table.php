<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations_sociales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('lien');
            $table->string('type')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'envoye', 'echec', 'non_autorise'])->default('en_attente');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['boutique_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destinations_sociales');
    }
};
