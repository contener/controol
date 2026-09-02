<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'date_naissance',
        'ville',
        'telephone',
        'whatsapp',
        'locale',
        'theme',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
        'admin_permissions_liste',
    ];

    /**
     * Reflète le défaut SQL de la colonne est_actif (voir migration) sur l'objet PHP
     * immédiatement après User::create(), sans attendre un fresh()/refresh() — Eloquent
     * ne relit pas automatiquement les colonnes à défaut SQL après un INSERT, l'attribut
     * resterait sinon null en mémoire (falsy) alors que la ligne réelle vaut 1 en base.
     */
    protected $attributes = [
        'est_actif' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_naissance' => 'date',
            'est_actif' => 'boolean',
        ];
    }

    /**
     * NOTE : 'role' est délibérément absent de $fillable ci-dessus. Un changement de
     * rôle ne doit jamais pouvoir transiter par une requête utilisateur (formulaire de
     * profil, payload JSON forgé...) — uniquement via `php artisan user:make-super-admin`
     * ou un forceFill() explicite côté serveur.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    /**
     * Porte d'entrée de tout l'espace /admin — distincte de isAdmin() qui n'a pas de
     * notion d'activation. Un admin désactivé (est_actif=false) perd tout accès à
     * l'espace d'administration mais reste un utilisateur normal de l'application
     * (aucun verrou sur la connexion elle-même).
     */
    public function canAccessAdminSpace(): bool
    {
        return $this->isSuperAdmin() || ($this->role === self::ROLE_ADMIN && $this->est_actif);
    }

    /**
     * Seule source de vérité pour "cet administrateur peut-il effectuer cette action" —
     * toujours interrogée fraîche via la relation adminPermissions (mise en cache par
     * instance/requête par Eloquent, jamais par un cache applicatif) : une permission
     * ajoutée ou retirée par le Super Admin doit prendre effet immédiatement, sans que
     * l'administrateur concerné ait besoin de se reconnecter.
     */
    public function hasAdminPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->canAccessAdminSpace() && $this->adminPermissions->pluck('permission')->contains($permission);
    }

    public function adminPermissions(): HasMany
    {
        return $this->hasMany(AdminPermission::class);
    }

    /**
     * Gardé par role === ROLE_ADMIN pour ne jamais interroger admin_permissions sur le
     * chemin chaud des utilisateurs normaux (auth.user est reconstruit à chaque requête
     * Inertia par Jetstream\ShareInertiaData) — n'a de sens que pour un compte admin.
     */
    protected function adminPermissionsListe(): Attribute
    {
        return Attribute::get(fn () => $this->role === self::ROLE_ADMIN
            ? $this->adminPermissions->pluck('permission')->values()->all()
            : []);
    }

    public function boutiques(): HasMany
    {
        return $this->hasMany(Boutique::class);
    }

    public function currentBoutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'current_boutique_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function abonnementActif(): ?Abonnement
    {
        return $this->abonnements()->actuellementActif()->latest('date_debut')->first();
    }

    public function planActif(): ?Plan
    {
        return $this->abonnementActif()?->plan;
    }

    public function switchBoutique(Boutique $boutique): void
    {
        if ($boutique->user_id !== $this->id) {
            return;
        }

        $this->forceFill(['current_boutique_id' => $boutique->id])->save();
    }
}
