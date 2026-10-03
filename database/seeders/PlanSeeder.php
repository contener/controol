<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'gratuit',
                'nom' => 'Gratuit',
                'prix' => 0,
                'devise' => 'XAF',
                'limite_boutiques' => 1,
                'limite_produits' => 10,
                'limite_clients' => 10,
                'limite_factures' => 10,
                'limite_stocks' => 2,
                'limite_destinations_sociales' => 0,
                'marketplace' => false,
                'publication_sociale' => false,
                'modeles_facture_avances' => false,
                'chatbot_whatsapp' => false,
                'audience' => false,
                'ordre' => 1,
            ],
            [
                'code' => 'basique',
                'nom' => 'Basique',
                'prix' => 5000,
                'lien_paiement' => 'https://zahletup.app/pay/GbCva5cy',
                'lien_paiement_promo' => 'https://zahletup.app/pay/mu2SyyTy',
                'devise' => 'XAF',
                'limite_boutiques' => 10,
                'limite_produits' => 100,
                'limite_clients' => 200,
                'limite_factures' => 100,
                'limite_stocks' => 20,
                'limite_destinations_sociales' => 10,
                'marketplace' => true,
                'publication_sociale' => false,
                'modeles_facture_avances' => true,
                'chatbot_whatsapp' => false,
                'audience' => true,
                'ordre' => 2,
            ],
            [
                'code' => 'pro',
                'nom' => 'Pro',
                'prix' => 15000,
                'lien_paiement' => 'https://zahletup.app/pay/9v94uwjV',
                'devise' => 'XAF',
                'limite_boutiques' => 100,
                'limite_produits' => null,
                'limite_clients' => null,
                'limite_factures' => null,
                'limite_stocks' => null,
                'limite_destinations_sociales' => null,
                'marketplace' => true,
                'publication_sociale' => true,
                'modeles_facture_avances' => true,
                'chatbot_whatsapp' => true,
                'audience' => true,
                'ordre' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
