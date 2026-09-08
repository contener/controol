<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('prenom')->nullable()->after('nom');
            $table->string('nom_famille')->nullable()->after('prenom');
            $table->json('telephones_secondaires')->nullable()->after('whatsapp');
            $table->json('emails_secondaires')->nullable()->after('email');
            $table->string('poste')->nullable()->after('entreprise');
            $table->string('adresse')->nullable()->after('ville');
            $table->string('region')->nullable()->after('adresse');
            $table->string('pays')->nullable()->after('region');
            $table->string('code_postal')->nullable()->after('pays');
            $table->date('date_anniversaire')->nullable()->after('code_postal');
        });

        Schema::table('contact_imports', function (Blueprint $table) {
            $table->string('delimiteur', 5)->default(',')->after('mapping_colonnes');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'prenom', 'nom_famille', 'telephones_secondaires', 'emails_secondaires',
                'poste', 'adresse', 'region', 'pays', 'code_postal', 'date_anniversaire',
            ]);
        });

        Schema::table('contact_imports', function (Blueprint $table) {
            $table->dropColumn('delimiteur');
        });
    }
};
