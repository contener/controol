<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactureLigne extends Model
{
    protected $table = 'facture_lignes';

    protected $fillable = [
        'facture_id',
        'produit_id',
        'designation',
        'description',
        'quantite',
        'prix_unitaire',
        'tva_taux',
        'remise_ligne',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'decimal:2',
            'prix_unitaire' => 'decimal:2',
            'tva_taux' => 'decimal:2',
            'remise_ligne' => 'decimal:2',
            'montant_ht' => 'decimal:2',
            'montant_tva' => 'decimal:2',
            'montant_ttc' => 'decimal:2',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
