<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
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
