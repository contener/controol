<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement en ligne des abonnements (CamerPay, 2026-11-24). Une "tentative" = un
 * passage par la page de paiement du prestataire pour un Paiement en attente (un
 * utilisateur peut abandonner puis réessayer : jamais d'écrasement de l'historique).
 * La confirmation n'est jamais déduite de l'application : seule une vérification
 * serveur auprès de CamerPay (statut "completed", montant et référence identiques)
 * approuve le Paiement -- voir PaiementEnLigneService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tentatives_paiement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('prestataire', 20)->default('camerpay');
            // Référence envoyée au prestataire (merchant_invoice_id) : aléatoire,
            // impossible à deviner, jamais l'identifiant interne du paiement.
            $table->string('reference_marchand', 40)->unique();
            // Identifiant de transaction renvoyé par le prestataire (uuid).
            $table->string('reference_prestataire', 64)->nullable()->unique();
            $table->decimal('montant', 12, 2);
            $table->string('devise', 3);
            $table->string('statut', 20)->default('en_cours'); // en_cours | confirmee | echouee
            $table->string('statut_prestataire', 30)->nullable();
            // Empreinte SHA-256 de la dernière notification reçue (traçabilité sans
            // conserver les données brutes du prestataire).
            $table->string('empreinte_notification', 64)->nullable();
            $table->timestamp('verifiee_le')->nullable();
            $table->timestamp('confirmee_le')->nullable();
            $table->timestamps();

            $table->index(['paiement_id', 'statut']);
        });

        // Une approbation automatique (paiement en ligne vérifié) n'a pas d'administrateur.
        Schema::table('paiement_audits', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentatives_paiement');
    }
};
