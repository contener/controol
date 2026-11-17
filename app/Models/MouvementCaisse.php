<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne du grand livre de la caisse -- créée uniquement par CaisseService, jamais
 * modifiée ni supprimée (voir la migration create_mouvements_caisse_table).
 */
class MouvementCaisse extends Model
{
    use BelongsToBoutique;

    public const ENTREE = 'entree';

    public const SORTIE = 'sortie';

    public const ORIGINES = ['apport', 'retrait', 'vente', 'depense'];

    protected $table = 'mouvements_caisse';

    protected $fillable = [
        'boutique_id',
        'sens',
        'origine',
        'montant',
        'libelle',
        'facture_id',
        'depense_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function depense(): BelongsTo
    {
        return $this->belongsTo(Depense::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
