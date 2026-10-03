<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grand livre des interactions -- jamais modifié après création (même principe que
 * commissions_parrainage/paiement_audits). `type` reste une chaîne libre, jamais un
 * enum rigide en base, pour pouvoir ajouter des types d'interaction plus tard
 * (vues, commentaires...) sans nouvelle migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audience_membre_id')->constrained('audience_membres')->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['boutique_id', 'created_at']);
            $table->index(['audience_membre_id', 'created_at']);
            $table->index(['produit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_interactions');
    }
};
