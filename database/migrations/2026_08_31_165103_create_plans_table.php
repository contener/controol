<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('nom');
            $table->decimal('prix', 10, 2)->default(0);
            $table->string('devise', 3)->default('XAF');
            $table->unsignedInteger('duree_jours')->default(30);
            $table->unsignedInteger('limite_boutiques')->nullable();
            $table->unsignedInteger('limite_produits')->nullable();
            $table->unsignedInteger('limite_clients')->nullable();
            $table->unsignedInteger('limite_factures')->nullable();
            $table->unsignedInteger('limite_stocks')->nullable();
            $table->boolean('marketplace')->default(false);
            $table->boolean('publication_sociale')->default(false);
            $table->boolean('chatbot_whatsapp')->default(false);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
