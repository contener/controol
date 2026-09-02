<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampagneSociale extends Model
{
    use BelongsToBoutique;

    protected $table = 'campagnes_sociales';

    protected $fillable = [
        'boutique_id',
        'message',
        'statut',
        'intervalle_secondes',
        'created_by',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campagneDestinations(): HasMany
    {
        return $this->hasMany(CampagneDestination::class, 'campagne_id');
    }

    public function estEnCours(): bool
    {
        return $this->statut === 'en_cours';
    }
}
