<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AudienceMembre extends Model
{
    use BelongsToBoutique;

    protected $fillable = [
        'boutique_id',
        'user_id',
        'visiteur_token',
        'nom',
        'contact',
        'statut',
    ];

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(AudienceInteraction::class);
    }

    /**
     * Même pattern que Conversation::dernierMessage() -- la "dernière interaction"
     * n'est jamais stockée, toujours résolue via latestOfMany().
     */
    public function derniereInteraction(): HasOne
    {
        return $this->hasOne(AudienceInteraction::class)->latestOfMany('created_at');
    }

    public function estAnonyme(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Pour un compte connecté, toujours lu en direct (jamais périmé) -- nom/contact
     * stockés sur ce modèle ne servent que de repli pour un visiteur anonyme, qui n'a
     * pas de ligne users à interroger.
     */
    public function nomAffiche(): string
    {
        return $this->user?->name ?? $this->nom ?? 'Visiteur anonyme';
    }

    public function contactAffiche(): ?string
    {
        return $this->user?->numeroWhatsapp() ?? $this->contact;
    }
}
