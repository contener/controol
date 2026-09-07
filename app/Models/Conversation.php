<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use BelongsToBoutique;

    public const STATUT_OUVERTE = 'ouverte';

    public const STATUT_ARCHIVEE = 'archivee';

    public const STATUT_FERMEE = 'fermee';

    public const STATUT_BLOQUEE = 'bloquee';

    public const STATUTS = [self::STATUT_OUVERTE, self::STATUT_ARCHIVEE, self::STATUT_FERMEE, self::STATUT_BLOQUEE];

    protected $fillable = [
        'boutique_id',
        'produit_id',
        'client_id',
        'visiteur_user_id',
        'visiteur_token',
        'visiteur_nom',
        'visiteur_contact',
        'statut',
        'messages_non_lus_boutique',
        'messages_non_lus_visiteur',
        'dernier_message_a',
    ];

    protected $appends = ['en_attente_de'];

    protected function casts(): array
    {
        return [
            'dernier_message_a' => 'datetime',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function visiteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visiteur_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('created_at');
    }

    public function dernierMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    public function scopeOuvertes(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_OUVERTE);
    }

    /**
     * Dérivé, jamais persisté : "à qui de répondre" se déduit uniquement de l'expéditeur
     * du dernier message — pas une seconde source de vérité à garder synchronisée.
     */
    protected function enAttenteDe(): Attribute
    {
        return Attribute::get(function () {
            $dernier = $this->relationLoaded('dernierMessage') ? $this->dernierMessage : $this->messages()->latest()->first();

            return match ($dernier?->expediteur) {
                ConversationMessage::EXPEDITEUR_VISITEUR => 'boutique',
                ConversationMessage::EXPEDITEUR_BOUTIQUE => 'visiteur',
                default => null,
            };
        });
    }
}
