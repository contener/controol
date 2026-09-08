<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contact_logs', function (Blueprint $table) {
            $table->id();
            // Pas de contrainte FK stricte sur user_id : la trace doit survivre à une
            // suppression de compte — même raisonnement que admin_audits.resource_id.
            $table->unsignedBigInteger('user_id');
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('numero_whatsapp');
            $table->text('message');
            $table->string('modele_cle')->nullable();
            $table->timestamp('ouvert_a');
            $table->timestamp('confirme_a')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contact_logs');
    }
};
