<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DestinationSociale extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $table = 'destinations_sociales';

    protected $fillable = [
        'boutique_id',
        'nom',
        'lien',
        'type',
        'statut',
        'ordre',
    ];

    public function campagneDestinations(): HasMany
    {
        return $this->hasMany(CampagneDestination::class);
    }
}
