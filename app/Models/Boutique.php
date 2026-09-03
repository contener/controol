<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Boutique extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom',
        'slug',
        'logo_path',
        'banniere_path',
        'description',
        'categorie',
        'adresse',
        'ville',
        'pays',
        'telephone',
        'whatsapp',
        'email',
        'devise',
        'taux_tva_defaut',
        'nui',
        'note_pied_facture',
        'statut',
        'facebook_url',
        'instagram_url',
        'telegram_url',
        'marketplace_visible',
    ];

    protected function casts(): array
    {
        return [
            'marketplace_visible' => 'boolean',
            'marketplace_disabled_by_admin' => 'boolean',
        ];
    }

    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function depenses(): HasMany
    {
        return $this->hasMany(Depense::class);
    }

    public function destinationsSociales(): HasMany
    {
        return $this->hasMany(DestinationSociale::class)->orderBy('ordre');
    }

    public function campagnesSociales(): HasMany
    {
        return $this->hasMany(CampagneSociale::class);
    }

    public function estActive(): bool
    {
        return $this->statut === 'active';
    }

    /**
     * Marketplace ≠ boutique publique : une boutique gratuite reste accessible par son
     * lien direct (/boutique/{slug}) mais n'apparaît jamais dans /marketplace. Seule
     * cette méthode fait foi côté serveur — jamais un simple filtrage frontend.
     */
    public function estEligibleMarketplace(): bool
    {
        if (! $this->estActive() || ! $this->marketplace_visible || $this->marketplace_disabled_by_admin) {
            return false;
        }

        $plan = $this->proprietaire->planActif();

        return $plan !== null && (bool) $plan->marketplace;
    }

    public function scopeEligiblesMarketplace(Builder $query): Builder
    {
        return $query
            ->where('statut', 'active')
            ->where('marketplace_visible', true)
            ->where('marketplace_disabled_by_admin', false)
            ->whereHas('proprietaire.abonnements', function (Builder $q) {
                $q->actuellementActif()->whereHas('plan', function (Builder $pq) {
                    $pq->where('marketplace', true);
                });
            });
    }
}
