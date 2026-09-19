<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationUtilisateur extends Model
{
    protected $table = 'notifications_utilisateurs';

    // La table n'a pas de colonne updated_at (une notification n'est jamais modifiée
    // après création, seulement marquée lue via lu_a) — Eloquent gère created_at seul.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'est_promotionnelle',
        'lu_a',
    ];

    protected function casts(): array
    {
        return [
            'est_promotionnelle' => 'boolean',
            'lu_a' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marquerLu(): void
    {
        if ($this->lu_a === null) {
            $this->update(['lu_a' => now()]);
        }
    }
}
