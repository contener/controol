<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essais_utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained('abonnements')->nullOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->timestamp('date_debut');
            $table->timestamp('date_fin');
            // Snapshot au moment de l'octroi : un changement futur du tarif promo ne
            // doit jamais affecter rétroactivement un essai déjà en cours.
            $table->decimal('prix_promo', 12, 2);
            $table->timestamp('converti_a')->nullable();
            $table->timestamp('annule_a')->nullable();
            // Anti-doublon pour la commande essais:notifier — une relance du process
            // ne doit jamais créer deux notifications pour le même jour d'essai.
            $table->unsignedTinyInteger('dernier_jour_notifie')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essais_utilisateurs');
    }
};
