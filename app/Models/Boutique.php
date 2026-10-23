<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function whatsAppAgent(): HasOne
    {
        return $this->hasOne(WhatsAppAgent::class);
    }

    public function suivis(): HasMany
    {
        return $this->hasMany(Suivi::class);
    }

    /**
     * Les 7 fonctionnalités importantes dont l'accès se donne/retire individuellement
     * par collaborateur (voir autoriseCollaborateurPour()) -- une colonne booléenne par
     * entrée, jamais une table de permissions générique (sur-ingénierie pour 7 cases
     * fixes). Toujours false par défaut à la création d'un collaborateur : le
     * propriétaire doit cocher explicitement, jamais un accès automatique.
     */
    public const PERMISSIONS_COLLABORATEUR = [
        'acces_dashboard',
        'acces_clients',
        'acces_produits',
        'acces_factures',
        'acces_depenses',
        'acces_audience',
        'acces_messagerie',
    ];

    /**
     * Comptes créés par le propriétaire pour l'aider à gérer cette boutique au
     * quotidien (plan Basique/Pro, voir plans.personnel_boutique) -- table de liaison
     * avec une colonne par permission (voir PERMISSIONS_COLLABORATEUR). La boutique
     * elle-même, l'abonnement et la gestion des collaborateurs restent réservés au
     * seul propriétaire, jamais une permission accordable.
     */
    public function collaborateurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'boutique_collaborateurs')
            ->withTimestamps()
            ->withPivot(self::PERMISSIONS_COLLABORATEUR);
    }

    /**
     * Point unique réutilisé par les Policies qui n'ont volontairement pas de
     * permission dédiée (DestinationSociale, CampagneSociale -- hors périmètre de la
     * demande de permissions granulaires) et par les endroits qui ont seulement besoin
     * de savoir "propriétaire OU collaborateur quelconque", sans distinction de
     * fonctionnalité (ex. masquer le bouton "Suivre" sur la page publique).
     */
    public function estGereePar(User $user): bool
    {
        return $user->id === $this->user_id || $this->collaborateurs()->whereKey($user->id)->exists();
    }

    /**
     * Point unique réutilisé par les Policies/contrôleurs des 7 fonctionnalités
     * importantes (Dashboard, Clients, Produits/Stock, Factures, Dépenses, Audience,
     * Messagerie) -- le propriétaire a toujours accès à tout ; un collaborateur
     * uniquement si la permission correspondante a été explicitement cochée pour lui.
     */
    public function autoriseCollaborateurPour(User $user, string $permission): bool
    {
        if ($user->id === $this->user_id) {
            return true;
        }

        $pivot = $this->collaborateurs()->whereKey($user->id)->first()?->pivot;

        return $pivot !== null && (bool) $pivot->{$permission};
    }

    public function autoriseDashboard(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_dashboard');
    }

    public function autoriseClients(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_clients');
    }

    public function autoriseProduits(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_produits');
    }

    public function autoriseFactures(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_factures');
    }

    public function autoriseDepenses(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_depenses');
    }

    public function autoriseAudience(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_audience');
    }

    public function autoriseMessagerie(User $user): bool
    {
        return $this->autoriseCollaborateurPour($user, 'acces_messagerie');
    }

    public function nombreAbonnes(): int
    {
        return $this->suivis()->actifs()->count();
    }

    public function estActive(): bool
    {
        return $this->statut === 'active';
    }

    /**
     * Même logique que estEligibleMarketplace() : un simple flag booléen du plan actif,
     * jamais fait confiance à une valeur envoyée par le client (cf. WhatsAppAgentController).
     */
    public function agentIaAutorise(): bool
    {
        return (bool) $this->proprietaire->planActif()?->chatbot_whatsapp;
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
