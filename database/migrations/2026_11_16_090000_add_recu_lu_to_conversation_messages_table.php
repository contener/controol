<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statut de remise façon WhatsApp : envoyé (les deux null), reçu (recu_le -- le
 * destinataire est connecté et son application a synchronisé la messagerie),
 * lu (lu_le -- le destinataire a ouvert la conversation). Les messages existants
 * restent "envoyés" ; ceux déjà lus le deviendront à la prochaine ouverture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->timestamp('recu_le')->nullable()->after('contenu');
            $table->timestamp('lu_le')->nullable()->after('recu_le');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn(['recu_le', 'lu_le']);
        });
    }
};
