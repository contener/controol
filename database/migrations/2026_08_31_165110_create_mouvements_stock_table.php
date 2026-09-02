<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['entree', 'sortie', 'ajustement']);
            $table->integer('quantite');
            $table->integer('quantite_avant');
            $table->integer('quantite_apres');
            $table->string('motif')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facture_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->timestamps();

            $table->index(['boutique_id', 'produit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
