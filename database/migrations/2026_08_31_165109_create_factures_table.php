<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('numero');
            $table->enum('statut', ['brouillon', 'envoyee', 'payee', 'annulee'])->default('brouillon');
            $table->date('date_emission');
            $table->date('date_echeance')->nullable();
            $table->decimal('sous_total', 14, 2)->default(0);
            $table->decimal('remise', 14, 2)->default(0);
            $table->decimal('total_tva', 14, 2)->default(0);
            $table->decimal('total_ttc', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['boutique_id', 'numero']);
            $table->index(['boutique_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
