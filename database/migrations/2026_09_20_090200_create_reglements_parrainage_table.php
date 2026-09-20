<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglements_parrainage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parrain_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('montant', 10, 2);
            $table->string('methode_paiement')->nullable();
            $table->string('reference_transaction')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('parrain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglements_parrainage');
    }
};
