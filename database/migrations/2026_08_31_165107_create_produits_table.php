<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['produit', 'service']);
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('prix_achat', 12, 2)->nullable();
            $table->decimal('prix_vente', 12, 2);
            $table->string('unite')->default('pièce');
            $table->decimal('tva_taux', 5, 2)->nullable();
            $table->boolean('gere_stock')->default(false);
            $table->integer('quantite_stock')->default(0);
            $table->integer('seuil_alerte')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('actif')->default(true);
            $table->decimal('promotion_prix', 12, 2)->nullable();
            $table->string('categorie')->nullable();
            $table->timestamps();

            $table->index(['boutique_id', 'type']);
            $table->index(['boutique_id', 'actif']);
            $table->unique(['boutique_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
