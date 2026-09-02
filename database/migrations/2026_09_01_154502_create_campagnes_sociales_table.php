<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_sociales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->enum('statut', ['brouillon', 'en_cours', 'terminee', 'arretee'])->default('brouillon');
            $table->unsignedInteger('intervalle_secondes')->default(30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['boutique_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagnes_sociales');
    }
};
