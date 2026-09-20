<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Généré à la demande (premier accès à la page Parrainage), jamais à
            // l'inscription -- volontairement différent de l'id interne (sécurité).
            $table->string('code_parrainage')->nullable()->unique()->after('role');

            // Un seul parrain par utilisateur, attribué une seule fois à l'inscription
            // et jamais modifié ensuite -- nullOnDelete : la suppression du compte du
            // parrain ne doit jamais affecter le filleul.
            $table->foreignId('parrain_id')->nullable()->after('code_parrainage')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parrain_id');
            $table->dropColumn('code_parrainage');
        });
    }
};
