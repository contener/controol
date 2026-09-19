<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenements_invitation_boutique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Visiteur anonyme : jeton opaque (VisiteurIdentiteService), jamais de
            // donnée personnelle -- cf. section sécurité du cahier des charges.
            $table->string('visiteur_token')->nullable();
            // Type libre (pas d'enum DB) : lien_visite, compte_cree, boutique_creee,
            // abonnement_cree utilisés dès cette phase ; d'autres types (ex.
            // popup_affiche/popup_ferme) pourront être journalisés plus tard sans
            // nouvelle migration.
            $table->string('type_evenement');
            $table->timestamp('created_at')->nullable();

            $table->index(['boutique_id', 'type_evenement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_invitation_boutique');
    }
};
