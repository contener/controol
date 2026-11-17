<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caisse de boutique (2026-11-17), modèle "grand livre" : chaque entrée/sortie
 * d'argent est une ligne jamais modifiée ni supprimée ; le solde n'est jamais stocké,
 * toujours recalculé (Σ entrées − Σ sorties). Une correction (facture annulée,
 * dépense modifiée...) ajoute une ligne de sens inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_caisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->string('sens'); // entree | sortie
            $table->string('origine'); // apport | retrait | vente | depense
            $table->decimal('montant', 14, 2);
            $table->string('libelle');
            // Liens conservés pour l'historique ; à la suppression du document, la
            // ligne reste (le libellé garde son numéro) et le lien passe à null.
            $table->foreignId('facture_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('depense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['boutique_id', 'created_at']);
        });

        Schema::table('boutiques', function (Blueprint $table) {
            // Les factures et dépenses créées AVANT cette date ne touchent jamais la
            // caisse (elles n'y ont jamais été enregistrées : les corriger plus tard
            // ne doit pas produire de fausses corrections). Null = depuis la création
            // de la boutique (toutes les boutiques créées après cette migration).
            $table->timestamp('caisse_ouverte_le')->nullable();
        });

        DB::table('boutiques')->update(['caisse_ouverte_le' => now()]);
    }

    public function down(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            $table->dropColumn('caisse_ouverte_le');
        });

        Schema::dropIfExists('mouvements_caisse');
    }
};
