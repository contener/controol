<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Depense extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $fillable = [
        'boutique_id',
        'categorie',
        'montant',
        'description',
        'date_depense',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_depense' => 'date',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
