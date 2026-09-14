<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nom')->default('Assistant');
            $table->boolean('actif')->default(false);
            $table->string('langue', 10)->default('fr');
            $table->string('personnalite')->nullable();
            $table->string('ton')->nullable();
            $table->text('message_accueil')->nullable();
            $table->text('message_hors_horaires')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_agents');
    }
};
