<?php

namespace App\Models;

use App\Enums\FactureModele;
use App\Enums\TypeFacture;
use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facture extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $fillable = [
        'boutique_id',
        'client_id',
        'type',
        'numero',
        'statut',
        'modele_id',
        'date_emission',
        'date_echeance',
        'sous_total',
        'remise',
        'total_tva',
        'total_ttc',
        'notes',
        'garantie',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeFacture::class,
            'modele_id' => FactureModele::class,
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'sous_total' => 'decimal:2',
            'remise' => 'decimal:2',
            'total_tva' => 'decimal:2',
            'total_ttc' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class)->orderBy('ordre');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function estModifiable(): bool
    {
        return $this->statut === 'brouillon';
    }

    /**
     * Un proforma est un document indicatif, jamais un engagement de vente — il ne doit
     * jamais faire bouger le stock (voir FactureService), contrairement à une facture.
     */
    public function estProforma(): bool
    {
        return $this->type === TypeFacture::Proforma;
    }
}
