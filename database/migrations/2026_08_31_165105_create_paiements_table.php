<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('montant', 10, 2);
            $table->string('devise', 3)->default('XAF');
            $table->string('moyen_paiement')->default('manuel');
            // en_attente = PENDING, approuve = APPROVED, rejete = REJECTED.
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->string('reference_transaction')->nullable();
            $table->text('motif_rejet')->nullable();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
