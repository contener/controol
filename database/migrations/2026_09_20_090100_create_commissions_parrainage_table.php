<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions_parrainage', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete (comme paiements.user_id) : une commission n'a de sens que
            // rattachée à un parrain/filleul/paiement bien réels -- si l'un des trois est
            // supprimé, la ligne de commission qui en dépend entièrement l'est aussi.
            $table->foreignId('parrain_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('filleul_id')->constrained('users')->cascadeOnDelete();
            // Unique : un paiement ne peut jamais générer deux commissions, garanti au
            // niveau base même en cas de double-appel accidentel du service.
            $table->foreignId('paiement_id')->unique()->constrained('paiements')->cascadeOnDelete();
            // Copies au moment de la création -- jamais recalculées après coup, même si
            // le taux change un jour (cohérent avec l'historique affiché).
            $table->decimal('montant_eligible', 10, 2);
            $table->decimal('taux', 5, 2);
            $table->decimal('montant_commission', 10, 2);
            // "disponible" ou "annulee" uniquement -- pas de statut "payée" ici : le
            // montant déjà réglé se calcule depuis reglements_parrainage, jamais en
            // modifiant une ligne de commission (cf. règlements partiels).
            $table->string('statut')->default('disponible');
            $table->foreignId('annule_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('annule_at')->nullable();
            $table->text('motif_annulation')->nullable();
            $table->timestamps();

            $table->index(['parrain_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions_parrainage');
    }
};
