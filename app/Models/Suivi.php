<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suivi extends Model
{
    protected $table = 'suivis_boutique';

    protected $fillable = [
        'boutique_id',
        'user_id',
        'notifications_actives',
        'abonne_a',
        'desabonne_a',
    ];

    protected function casts(): array
    {
        return [
            'notifications_actives' => 'boolean',
            'abonne_a' => 'datetime',
            'desabonne_a' => 'datetime',
        ];
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estActif(): bool
    {
        return $this->desabonne_a === null;
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->whereNull('desabonne_a');
    }
}
