<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_APPROUVE = 'approuve';

    public const STATUT_REJETE = 'rejete';

    protected $fillable = [
        'user_id',
        'abonnement_id',
        'montant',
        'devise',
        'moyen_paiement',
        'statut',
        'reference_transaction',
        'motif_rejet',
        'valide_par',
        'valide_at',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'valide_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PaiementAudit::class);
    }

    public function estEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }
}
