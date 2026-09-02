<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'code',
        'nom',
        'prix',
        'lien_paiement',
        'devise',
        'duree_jours',
        'limite_boutiques',
        'limite_produits',
        'limite_clients',
        'limite_factures',
        'limite_stocks',
        'limite_destinations_sociales',
        'marketplace',
        'publication_sociale',
        'modeles_facture_avances',
        'chatbot_whatsapp',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'prix' => 'decimal:2',
            'marketplace' => 'boolean',
            'publication_sociale' => 'boolean',
            'modeles_facture_avances' => 'boolean',
            'chatbot_whatsapp' => 'boolean',
        ];
    }
}
