<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visiteur_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('visiteur_token')->nullable();
            $table->string('visiteur_nom');
            $table->string('visiteur_contact')->nullable();
            $table->string('statut')->default('ouverte');
            $table->unsignedInteger('messages_non_lus_boutique')->default(0);
            $table->unsignedInteger('messages_non_lus_visiteur')->default(0);
            $table->timestamp('dernier_message_a')->nullable();
            $table->timestamps();

            $table->index(['boutique_id', 'produit_id', 'visiteur_token'], 'conversations_boutique_produit_token_idx');
            $table->index(['boutique_id', 'produit_id', 'visiteur_user_id'], 'conversations_boutique_produit_user_idx');
            $table->index(['boutique_id', 'statut'], 'conversations_boutique_statut_idx');
            $table->index('visiteur_user_id');
            $table->index('visiteur_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
