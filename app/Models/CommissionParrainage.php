<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionParrainage extends Model
{
    protected $table = 'commissions_parrainage';

    protected $fillable = [
        'parrain_id',
        'filleul_id',
        'paiement_id',
        'montant_eligible',
        'taux',
        'montant_commission',
        'statut',
        'annule_par',
        'annule_at',
        'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'montant_eligible' => 'decimal:2',
            'taux' => 'decimal:2',
            'montant_commission' => 'decimal:2',
            'annule_at' => 'datetime',
        ];
    }

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parrain_id');
    }

    public function filleul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filleul_id');
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function annulateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function estActive(): bool
    {
        return $this->statut !== 'annulee';
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('statut', '!=', 'annulee');
    }
}
