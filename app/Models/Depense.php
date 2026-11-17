<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use App\Services\CaisseService;
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

    /**
     * Chaque dépense sort de la caisse (CaisseService) -- branché ici une seule fois
     * pour couvrir le site et l'application. 'deleting' : avant la suppression, tant
     * que le lien depense_id des mouvements de caisse existe encore.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $depense) => app(CaisseService::class)->synchroniserDepense($depense));
        static::deleting(fn (self $depense) => app(CaisseService::class)->synchroniserDepense($depense, supprimee: true));
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
