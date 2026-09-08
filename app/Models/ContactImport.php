<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactImport extends Model
{
    public const STATUT_EN_COURS = 'en_cours';

    public const STATUT_TERMINE = 'termine';

    public const STATUT_ECHOUE = 'echoue';

    protected $fillable = [
        'admin_id',
        'nom_fichier',
        'total_lignes',
        'lignes_importees',
        'lignes_maj',
        'lignes_ignorees',
        'lignes_erreur',
        'statut',
        'chemin_temporaire',
        'mapping_colonnes',
        'erreurs',
    ];

    protected function casts(): array
    {
        return [
            'mapping_colonnes' => 'array',
            'erreurs' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'import_id');
    }
}
