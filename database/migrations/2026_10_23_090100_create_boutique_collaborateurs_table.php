<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de liaison pure (pas de colonne rôle/permission en v1) : un collaborateur a un
 * accès complet aux opérations courantes de la boutique (voir Boutique::estGereePar()),
 * seules la gestion de la boutique elle-même, de l'abonnement et des collaborateurs
 * restent réservées au propriétaire. Jamais de table pivot Jetstream "team_user"
 * réutilisée -- ce mécanisme est entièrement indépendant (voir controol.md §5.17b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boutique_collaborateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['boutique_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boutique_collaborateurs');
    }
};
