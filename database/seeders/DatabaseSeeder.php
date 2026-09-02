<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\Client;
use App\Models\Depense;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\User;
use App\Services\FactureService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'ville' => 'Douala',
            'whatsapp' => '+237600000000',
        ]);
        $user->forceFill(['role' => User::ROLE_SUPER_ADMIN])->save();

        $planPro = Plan::where('code', 'pro')->first();
        $user->abonnements()->create([
            'plan_id' => $planPro->id,
            'statut' => 'actif',
            'date_debut' => now(),
        ]);

        $boutiqueInformatique = Boutique::create([
            'user_id' => $user->id,
            'nom' => 'Ma Boutique Informatique',
            'slug' => 'ma-boutique-informatique',
            'description' => 'Vente de matériel informatique et accessoires à Douala.',
            'categorie' => 'Informatique',
            'adresse' => 'Avenue Kennedy',
            'ville' => 'Douala',
            'telephone' => '+237 6 00 00 00 00',
            'whatsapp' => '+237600000000',
            'email' => 'contact@boutique-info.demo',
            'devise' => 'XAF',
            'taux_tva_defaut' => 19.25,
            'pays' => 'Cameroun',
            'marketplace_visible' => true,
        ]);

        Boutique::create([
            'user_id' => $user->id,
            'nom' => 'Ma Boutique Vêtements',
            'slug' => 'ma-boutique-vetements',
            'description' => 'Prêt-à-porter homme et femme.',
            'categorie' => 'Vêtements',
            'ville' => 'Yaoundé',
            'pays' => 'Cameroun',
            'devise' => 'XAF',
            'taux_tva_defaut' => 19.25,
            'marketplace_visible' => true,
        ]);

        $user->switchBoutique($boutiqueInformatique);

        $clientClient = Client::create([
            'boutique_id' => $boutiqueInformatique->id,
            'nom' => 'Société Alpha SARL',
            'email' => 'contact@alpha.cm',
            'telephone' => '+237 6 11 11 11 11',
            'ville' => 'Douala',
            'pays' => 'Cameroun',
            'etiquette' => 'client',
        ]);

        Client::create([
            'boutique_id' => $boutiqueInformatique->id,
            'nom' => 'Jean Mbarga',
            'email' => 'jean.mbarga@example.com',
            'telephone' => '+237 6 22 22 22 22',
            'ville' => 'Yaoundé',
            'pays' => 'Cameroun',
            'etiquette' => 'prospect',
        ]);

        $ordinateur = Produit::create([
            'boutique_id' => $boutiqueInformatique->id,
            'type' => 'produit',
            'nom' => 'Ordinateur portable 15"',
            'reference' => 'PROD-001',
            'prix_achat' => 250000,
            'prix_vente' => 350000,
            'unite' => 'pièce',
            'tva_taux' => 19.25,
            'gere_stock' => true,
            'quantite_stock' => 12,
            'seuil_alerte' => 5,
            'actif' => true,
            'categorie' => 'Ordinateurs',
        ]);

        Produit::create([
            'boutique_id' => $boutiqueInformatique->id,
            'type' => 'produit',
            'nom' => 'Souris sans fil',
            'reference' => 'PROD-002',
            'prix_achat' => 3000,
            'prix_vente' => 6000,
            'unite' => 'pièce',
            'tva_taux' => 19.25,
            'gere_stock' => true,
            'quantite_stock' => 3,
            'seuil_alerte' => 10,
            'actif' => true,
            'categorie' => 'Accessoires',
        ]);

        $installation = Produit::create([
            'boutique_id' => $boutiqueInformatique->id,
            'type' => 'service',
            'nom' => 'Installation et configuration',
            'reference' => 'SERV-001',
            'prix_vente' => 25000,
            'unite' => 'heure',
            'tva_taux' => 19.25,
            'gere_stock' => false,
            'actif' => true,
            'categorie' => 'Services',
        ]);

        app(FactureService::class)->creer([
            'client_id' => $clientClient->id,
            'date_emission' => now()->toDateString(),
            'date_echeance' => now()->addDays(30)->toDateString(),
            'remise' => 0,
            'notes' => 'Merci de votre confiance.',
            'statut' => 'envoyee',
            'lignes' => [
                [
                    'produit_id' => $ordinateur->id,
                    'designation' => $ordinateur->nom,
                    'quantite' => 2,
                    'prix_unitaire' => $ordinateur->prix_vente,
                    'tva_taux' => $ordinateur->tva_taux,
                    'remise_ligne' => 0,
                ],
                [
                    'produit_id' => $installation->id,
                    'designation' => $installation->nom,
                    'quantite' => 1,
                    'prix_unitaire' => $installation->prix_vente,
                    'tva_taux' => $installation->tva_taux,
                    'remise_ligne' => 0,
                ],
            ],
        ], $user, $boutiqueInformatique->id);

        Depense::create([
            'boutique_id' => $boutiqueInformatique->id,
            'categorie' => 'Loyer',
            'montant' => 150000,
            'description' => 'Loyer du local commercial',
            'date_depense' => now()->startOfMonth()->toDateString(),
            'created_by' => $user->id,
        ]);

        Depense::create([
            'boutique_id' => $boutiqueInformatique->id,
            'categorie' => 'Transport',
            'montant' => 15000,
            'description' => 'Livraison fournisseur',
            'date_depense' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        // Deuxième utilisateur de démo, avec une demande de paiement PENDING à traiter
        // dans /admin/paiements — pour vérifier tout de suite le flux d'approbation.
        $autreUtilisateur = User::factory()->create([
            'name' => 'Aïcha Ndongo',
            'email' => 'aicha@example.com',
            'ville' => 'Yaoundé',
            'telephone' => '+237 6 55 55 55 55',
        ]);

        $planBasique = Plan::where('code', 'basique')->first();
        $abonnementEnAttente = $autreUtilisateur->abonnements()->create([
            'plan_id' => $planBasique->id,
            'statut' => 'en_attente',
            'date_debut' => now(),
        ]);

        Paiement::create([
            'user_id' => $autreUtilisateur->id,
            'abonnement_id' => $abonnementEnAttente->id,
            'montant' => $planBasique->prix,
            'devise' => $planBasique->devise,
            'moyen_paiement' => 'Orange Money',
            'reference_transaction' => 'OM-'.now()->format('YmdHis'),
            'statut' => 'en_attente',
        ]);

        // Troisième utilisateur : plan Gratuit avec marketplace_visible=true malgré tout
        // — démontre que la Marketplace reste bien gouvernée par le plan (règle 4/5 du
        // cahier des charges), pas seulement par la préférence du propriétaire.
        $utilisateurGratuit = User::factory()->create([
            'name' => 'Paul Eto',
            'email' => 'paul@example.com',
            'ville' => 'Bafoussam',
        ]);

        $planGratuit = Plan::where('code', 'gratuit')->first();
        $utilisateurGratuit->abonnements()->create([
            'plan_id' => $planGratuit->id,
            'statut' => 'actif',
            'date_debut' => now(),
        ]);

        $boutiqueGratuite = Boutique::create([
            'user_id' => $utilisateurGratuit->id,
            'nom' => 'Petite Boutique Gratuite',
            'slug' => 'petite-boutique-gratuite',
            'description' => 'Accessoires de mode à petit prix.',
            'categorie' => 'Vêtements',
            'ville' => 'Bafoussam',
            'pays' => 'Cameroun',
            'devise' => 'XAF',
            'marketplace_visible' => true,
        ]);

        Produit::create([
            'boutique_id' => $boutiqueGratuite->id,
            'type' => 'produit',
            'nom' => 'Casquette brodée',
            'prix_vente' => 5000,
            'unite' => 'pièce',
            'actif' => true,
            'categorie' => 'Accessoires',
        ]);

        $utilisateurGratuit->switchBoutique($boutiqueGratuite);
    }
}
