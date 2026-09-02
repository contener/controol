<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boutiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('banniere_path')->nullable();
            $table->text('description')->nullable();
            $table->string('categorie')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('telephone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('devise', 3)->default('XAF');
            $table->decimal('taux_tva_defaut', 5, 2)->default(19.25);
            $table->enum('statut', ['active', 'suspendue'])->default('active');
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('telegram_url')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boutiques');
    }
};
