<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use BelongsToBoutique, HasFactory;

    /**
     * Seuls champs d'un produit montrés à un visiteur (page publique de la boutique,
     * Marketplace web et mobile) -- jamais prix_achat, quantite_stock, seuil_alerte,
     * reference, gere_stock : données commerciales confidentielles du vendeur (audit
     * sécurité V-03, 2026-11-14 -- elles étaient exposées dans la page publique).
     */
    public const CHAMPS_PUBLICS = [
        'id', 'nom', 'description', 'categorie', 'mini_characteristics',
        'photo_path', 'prix_vente', 'promotion_prix', 'unite',
    ];

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
        'marketplace_visible',
        'promotion_prix',
        'categorie',
        'mini_characteristics',
    ];

    protected function casts(): array
    {
        return [
            'gere_stock' => 'boolean',
            'actif' => 'boolean',
            'marketplace_visible' => 'boolean',
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

    public function interactionsAudience(): HasMany
    {
        return $this->hasMany(AudienceInteraction::class);
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
