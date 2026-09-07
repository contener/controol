<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            DB::table('messages')->orderBy('id')->each(function ($ligne) {
                $conversationId = DB::table('conversations')->insertGetId([
                    'boutique_id' => $ligne->boutique_id,
                    'produit_id' => $ligne->produit_id,
                    'visiteur_nom' => $ligne->nom_visiteur,
                    'visiteur_contact' => $ligne->contact_visiteur,
                    'statut' => 'ouverte',
                    'messages_non_lus_boutique' => $ligne->lu ? 0 : 1,
                    'dernier_message_a' => $ligne->created_at,
                    'created_at' => $ligne->created_at,
                    'updated_at' => $ligne->updated_at,
                ]);

                DB::table('conversation_messages')->insert([
                    'conversation_id' => $conversationId,
                    'expediteur' => 'visiteur',
                    'contenu' => $ligne->contenu,
                    'created_at' => $ligne->created_at,
                    'updated_at' => $ligne->updated_at,
                ]);
            });
        }

        Schema::dropIfExists('messages');
    }

    public function down(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nom_visiteur');
            $table->string('contact_visiteur')->nullable();
            $table->text('contenu');
            $table->boolean('lu')->default(false);
            $table->timestamps();
        });
    }
};
