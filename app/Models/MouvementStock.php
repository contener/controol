<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $table = 'mouvements_stock';

    protected $fillable = [
        'boutique_id',
        'produit_id',
        'type',
        'quantite',
        'quantite_avant',
        'quantite_apres',
        'motif',
        'user_id',
        'facture_id',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
