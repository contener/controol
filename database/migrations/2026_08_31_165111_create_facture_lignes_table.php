<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained('produits')->nullOnDelete();
            $table->string('designation');
            $table->text('description')->nullable();
            $table->decimal('quantite', 12, 2);
            $table->decimal('prix_unitaire', 14, 2);
            $table->decimal('tva_taux', 5, 2)->default(0);
            $table->decimal('remise_ligne', 14, 2)->default(0);
            $table->decimal('montant_ht', 14, 2);
            $table->decimal('montant_tva', 14, 2);
            $table->decimal('montant_ttc', 14, 2);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index('facture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_lignes');
    }
};
