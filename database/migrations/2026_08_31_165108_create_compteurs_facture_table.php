<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compteurs_facture', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamps();

            $table->unique(['boutique_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteurs_facture');
    }
};
