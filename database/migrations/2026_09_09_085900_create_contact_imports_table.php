<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('nom_fichier');
            $table->unsignedInteger('total_lignes')->default(0);
            $table->unsignedInteger('lignes_importees')->default(0);
            $table->unsignedInteger('lignes_maj')->default(0);
            $table->unsignedInteger('lignes_ignorees')->default(0);
            $table->unsignedInteger('lignes_erreur')->default(0);
            $table->string('statut')->default('en_cours');
            $table->string('chemin_temporaire')->nullable();
            $table->json('mapping_colonnes')->nullable();
            $table->json('erreurs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
    }
};
