<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $fillable = [
        'boutique_id',
        'type',
        'nom',
        'description',
        'reference',
        'prix_achat',
        'prix_vente',
        'unite',
        'tva_taux',
        'gere_stock',
        'quantite_stock',
        'seuil_alerte',
        'photo_path',
        'actif',
        'promotion_prix',
        'categorie',
        'mini_characteristics',
    ];

    protected function casts(): array
    {
        return [
            'gere_stock' => 'boolean',
            'actif' => 'boolean',
            'prix_achat' => 'decimal:2',
            'prix_vente' => 'decimal:2',
            'tva_taux' => 'decimal:2',
            'promotion_prix' => 'decimal:2',
        ];
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function enRupture(): bool
    {
        return $this->gere_stock && $this->seuil_alerte !== null && $this->quantite_stock <= $this->seuil_alerte;
    }

    public function prixAffiche(): float
    {
        return (float) ($this->promotion_prix ?? $this->prix_vente);
    }
}
