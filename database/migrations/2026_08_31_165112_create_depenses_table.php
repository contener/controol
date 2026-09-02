<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->string('categorie');
            $table->decimal('montant', 14, 2);
            $table->text('description')->nullable();
            $table->date('date_depense');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['boutique_id', 'date_depense']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
    }
};
