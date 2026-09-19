<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modeles_notification_essai', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('jour')->unique();
            $table->string('titre');
            $table->text('message');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Seedés directement ici (pas via un Seeder séparé) : ces 7 lignes sont une
        // donnée de référence nécessaire au fonctionnement de la fonctionnalité, pas
        // une donnée de démo optionnelle — elles doivent exister dès le premier
        // déploiement sans étape manuelle supplémentaire. Éditables ensuite par le
        // Super Admin (cf. cahier des charges §4 : "modèles modifiables, pas codés en
        // dur").
        $maintenant = now();
        $modeles = [
            1 => [
                'titre' => 'Bienvenue dans votre essai Basique !',
                'message' => "Bonjour {{nom_utilisateur}} 👋\nVotre période d'essai du plan Basique vient de commencer.\nVous disposez de 7 jours pour découvrir les fonctionnalités avancées de CONTROOL.\nProfitez de notre offre spéciale : {{prix_promotionnel}} FCFA au lieu de {{prix_normal}} FCFA.\nVotre essai se termine le {{date_fin}}.",
            ],
            2 => [
                'titre' => 'Votre essai Basique continue',
                'message' => "Bonjour {{nom_utilisateur}} 👋\nIl vous reste {{jours_restants}} jours d'essai du plan Basique.\nExplorez les fonctionnalités avancées et profitez de l'offre à {{prix_promotionnel}} FCFA au lieu de {{prix_normal}} FCFA.\nFin de l'essai : {{date_fin}}.",
            ],
            3 => [
                'titre' => 'Votre essai Basique continue',
                'message' => "Bonjour {{nom_utilisateur}} 👋\nIl vous reste {{jours_restants}} jours d'essai du plan Basique.\nContinuez à découvrir les fonctionnalités de CONTROOL et profitez de l'offre promotionnelle à {{prix_promotionnel}} FCFA au lieu de {{prix_normal}} FCFA.\nDate de fin : {{date_fin}}.",
            ],
            4 => [
                'titre' => 'Votre essai Basique continue',
                'message' => "Bonjour {{nom_utilisateur}} 👋\nIl vous reste {{jours_restants}} jours d'essai du plan Basique.\nProfitez-en pour tester toutes les fonctionnalités avant la fin de votre essai.\nOffre : {{prix_promotionnel}} FCFA au lieu de {{prix_normal}} FCFA. Fin : {{date_fin}}.",
            ],
            5 => [
                'titre' => 'Votre essai Basique — bientôt la fin',
                'message' => "Bonjour {{nom_utilisateur}} 👋\nPlus que {{jours_restants}} jours d'essai du plan Basique.\nNe manquez pas notre offre à {{prix_promotionnel}} FCFA au lieu de {{prix_normal}} FCFA.\nFin de l'essai : {{date_fin}}.",
            ],
            6 => [
                'titre' => '⚠️ Votre essai se termine demain',
                'message' => "⚠️ Votre période d'essai se termine demain.\nIl vous reste 1 jour pour profiter des fonctionnalités Basique.\nAbonnez-vous pendant la période d'essai à {{prix_promotionnel}} FCFA au lieu de {{prix_normal}} FCFA.\nFin de l'essai : {{date_fin}}.",
            ],
            7 => [
                'titre' => '⏳ Dernier jour de votre essai',
                'message' => "⏳ Votre période d'essai du plan Basique se termine aujourd'hui.\nProfitez de notre offre à {{prix_promotionnel}} FCFA pour continuer à utiliser les fonctionnalités Basique.\nChoisissez votre abonnement avant la fin de votre essai.",
            ],
        ];

        foreach ($modeles as $jour => $modele) {
            DB::table('modeles_notification_essai')->insert([
                'jour' => $jour,
                'titre' => $modele['titre'],
                'message' => $modele['message'],
                'actif' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_notification_essai');
    }
};
