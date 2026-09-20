<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReglementParrainage extends Model
{
    protected $table = 'reglements_parrainage';

    // Un règlement, comme un PaiementAudit, n'est jamais modifié après création.
    const UPDATED_AT = null;

    protected $fillable = [
        'parrain_id',
        'montant',
        'methode_paiement',
        'reference_transaction',
        'notes',
        'traite_par',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parrain_id');
    }

    public function traiteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }
}
