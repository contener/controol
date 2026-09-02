<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'paiement_id',
        'user_id',
        'admin_id',
        'action',
        'statut_avant',
        'statut_apres',
        'motif',
        'ip_address',
    ];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
