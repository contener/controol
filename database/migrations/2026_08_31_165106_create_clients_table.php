<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->nullable();
            $table->string('numero_fiscal')->nullable();
            $table->text('notes')->nullable();
            $table->enum('etiquette', ['prospect', 'client'])->default('prospect');
            $table->timestamps();

            $table->index(['boutique_id', 'etiquette']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
