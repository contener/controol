<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $fillable = [
        'boutique_id',
        'nom',
        'email',
        'telephone',
        'adresse',
        'ville',
        'pays',
        'numero_fiscal',
        'notes',
        'etiquette',
    ];

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }
}
