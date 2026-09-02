<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiement_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('statut_avant', 20);
            $table->string('statut_apres', 20);
            $table->text('motif')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('paiement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_audits');
    }
};
