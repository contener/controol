<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampagneDestination extends Model
{
    protected $fillable = [
        'campagne_id',
        'destination_sociale_id',
        'statut',
        'traite_at',
    ];

    protected function casts(): array
    {
        return [
            'traite_at' => 'datetime',
        ];
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneSociale::class, 'campagne_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationSociale::class, 'destination_sociale_id');
    }
}
