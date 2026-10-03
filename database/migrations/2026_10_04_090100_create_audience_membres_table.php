<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne par personne unique et par boutique -- jamais de doublon, même si cette
 * personne interagit avec plusieurs produits ou plusieurs fois avec le même. Les
 * compteurs/dates agrégées ne sont JAMAIS stockés ici : toujours calculés à la volée
 * depuis audience_interactions (même principe que EssaiUtilisateur::statut(),
 * Suivi::estActif()). nom/contact ne sont que des instantanés pour un visiteur anonyme
 * (pas de compte User à interroger en direct) -- pour un compte connecté, toujours lu
 * depuis users.name/numeroWhatsapp(), jamais dupliqué ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_membres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('visiteur_token')->nullable();
            $table->string('nom')->nullable();
            $table->string('contact')->nullable();
            $table->string('statut')->default('actif');
            $table->timestamps();

            // Deux index UNIQUE séparés : chacun n'enforce l'unicité que sur ses
            // lignes non-NULL (comportement standard MySQL/SQLite) -- un compte
            // connecté n'a qu'une ligne par boutique, un visiteur anonyme (même
            // token) aussi, indépendamment l'un de l'autre.
            $table->unique(['boutique_id', 'user_id']);
            $table->unique(['boutique_id', 'visiteur_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_membres');
    }
};
