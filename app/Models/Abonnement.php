<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abonnement extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'statut',
        'date_debut',
        'date_fin',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Un abonnement "actif" en base peut avoir dépassé sa date_fin si la commande
     * planifiée abonnements:expirer n'est pas encore passée. Ce scope est le seul
     * point de vérité pour "cet abonnement autorise-t-il actuellement les droits du
     * plan ?" — utilisé partout (User::abonnementActif, éligibilité Marketplace,
     * administration) pour ne jamais dupliquer cette condition.
     */
    public function scopeActuellementActif(Builder $query): Builder
    {
        return $query
            ->where('statut', 'actif')
            ->where(function (Builder $q) {
                $q->whereNull('date_fin')->orWhere('date_fin', '>=', now());
            });
    }
}
