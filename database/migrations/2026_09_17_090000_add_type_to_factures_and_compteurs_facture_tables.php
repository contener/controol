<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent : une première tentative de cette migration a pu échouer en
        // production APRÈS avoir déjà ajouté ces deux colonnes (DDL MySQL non
        // transactionnel — chaque ALTER TABLE s'engage immédiatement), avant l'échec sur
        // l'étape d'index ci-dessous. Un simple hasColumn évite un "duplicate column" au
        // prochain passage, sans avoir besoin de trafiquer la table migrations à la main.
        if (! Schema::hasColumn('factures', 'type')) {
            Schema::table('factures', function (Blueprint $table) {
                $table->string('type', 20)->default('facture')->after('client_id');
            });
        }

        if (! Schema::hasColumn('compteurs_facture', 'type')) {
            Schema::table('compteurs_facture', function (Blueprint $table) {
                // Séquence de numérotation distincte par type : un proforma ne doit
                // jamais consommer un numéro de la séquence légale des factures.
                $table->string('type', 20)->default('facture')->after('annee');
            });
        }

        // L'index unique [boutique_id, annee] existant sert aussi de support à la
        // contrainte de clé étrangère sur boutique_id (MySQL exige qu'un index couvrant
        // la colonne FK existe en permanence). Il faut donc créer le NOUvel index avant
        // de supprimer l'ancien, jamais l'inverse — sinon MySQL refuse le DROP avec
        // l'erreur 1553 "needed in a foreign key constraint".
        if (! $this->indexExiste('compteurs_facture', 'compteurs_facture_boutique_id_annee_type_unique')) {
            Schema::table('compteurs_facture', function (Blueprint $table) {
                $table->unique(['boutique_id', 'annee', 'type']);
            });
        }

        if ($this->indexExiste('compteurs_facture', 'compteurs_facture_boutique_id_annee_unique')) {
            Schema::table('compteurs_facture', function (Blueprint $table) {
                $table->dropUnique(['boutique_id', 'annee']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('compteurs_facture', function (Blueprint $table) {
            $table->unique(['boutique_id', 'annee']);
        });

        Schema::table('compteurs_facture', function (Blueprint $table) {
            $table->dropUnique(['boutique_id', 'annee', 'type']);
            $table->dropColumn('type');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    private function indexExiste(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        return collect($connection->select("show index from {$table}"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
