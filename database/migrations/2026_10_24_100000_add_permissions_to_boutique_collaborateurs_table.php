<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jusqu'ici un collaborateur avait un accès complet et automatique dès son ajout
 * (voir la docblock d'origine de create_boutique_collaborateurs_table) -- le
 * propriétaire doit désormais cocher explicitement chaque fonctionnalité importante
 * (Dashboard, Clients, Produits/Stock, Factures, Dépenses, Audience, Messagerie).
 * Défaut false pour que l'ajout d'un nouveau collaborateur ne donne plus jamais
 * d'accès automatique -- voir Boutique::autoriseCollaborateurPour().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boutique_collaborateurs', function (Blueprint $table) {
            $table->boolean('acces_dashboard')->default(false);
            $table->boolean('acces_clients')->default(false);
            $table->boolean('acces_produits')->default(false);
            $table->boolean('acces_factures')->default(false);
            $table->boolean('acces_depenses')->default(false);
            $table->boolean('acces_audience')->default(false);
            $table->boolean('acces_messagerie')->default(false);
        });

        // Les collaborateurs déjà créés avant cette migration avaient un accès complet
        // implicite -- on le rend explicite plutôt que de les bloquer sans prévenir.
        // Seuls les NOUVEAUX collaborateurs (créés après cette migration) démarrent
        // sans aucun accès, conformément à la colonne par défaut ci-dessus.
        DB::table('boutique_collaborateurs')->update([
            'acces_dashboard' => true,
            'acces_clients' => true,
            'acces_produits' => true,
            'acces_factures' => true,
            'acces_depenses' => true,
            'acces_audience' => true,
            'acces_messagerie' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('boutique_collaborateurs', function (Blueprint $table) {
            $table->dropColumn([
                'acces_dashboard',
                'acces_clients',
                'acces_produits',
                'acces_factures',
                'acces_depenses',
                'acces_audience',
                'acces_messagerie',
            ]);
        });
    }
};
