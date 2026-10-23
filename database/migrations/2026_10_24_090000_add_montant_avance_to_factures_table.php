<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Propre aux Reçus (preuve de paiement partiel) : le montant déjà versé au moment de
 * l'émission. Le "reste à payer" n'est jamais stocké (calculé à la volée, voir
 * Facture::resteAPayer() -- même principe déjà appliqué partout dans ce projet pour
 * une donnée dérivable), et la "date à laquelle il faut donner le reste" réutilise le
 * champ date_echeance déjà existant sur toute facture, jamais une nouvelle colonne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->decimal('montant_avance', 14, 2)->nullable()->after('total_ttc');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('montant_avance');
        });
    }
};
