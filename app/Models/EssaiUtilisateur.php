<?php

namespace App\Models;

use App\Enums\EssaiStatut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EssaiUtilisateur extends Model
{
    protected $table = 'essais_utilisateurs';

    protected $fillable = [
        'user_id',
        'abonnement_id',
        'plan_id',
        'date_debut',
        'date_fin',
        'prix_promo',
        'converti_a',
        'annule_a',
        'dernier_jour_notifie',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'prix_promo' => 'decimal:2',
            'converti_a' => 'datetime',
            'annule_a' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Pas de colonne "statut" persistée : calculée à partir de converti_a/annule_a/
     * date_fin uniquement. date_fin est un instantané pris à la création, identique
     * à celui de l'abonnement lié, et ni l'un ni l'autre n'est jamais modifié en
     * dehors de ces deux marqueurs (converti_a est posé au moment même où
     * PaiementValidationService::approuver() clôt l'abonnement d'essai) : vérifier
     * en plus le statut brut de l'abonnement lié ajouterait une deuxième source de
     * vérité qui peut se désynchroniser (son statut ne change qu'une fois par jour,
     * via la commande planifiée abonnements:expirer), pas une garantie de plus.
     */
    public function statut(): EssaiStatut
    {
        if ($this->converti_a !== null) {
            return EssaiStatut::Converti;
        }

        if ($this->annule_a !== null) {
            return EssaiStatut::Annule;
        }

        if ($this->date_fin->isFuture()) {
            return EssaiStatut::EnCours;
        }

        return EssaiStatut::Expire;
    }

    public function joursRestants(): int
    {
        $heures = now()->diffInHours($this->date_fin, false);

        return max(0, (int) ceil($heures / 24));
    }

    /**
     * Jour de l'essai (1 à 7) — jour 1 = jour de l'inscription. Borné à [1, 7] pour
     * toujours correspondre à une ligne de modeles_notification_essai valide.
     */
    public function jourActuel(): int
    {
        $jours = (int) $this->date_debut->diffInDays(now()) + 1;

        return max(1, min(7, $jours));
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->whereNull('converti_a')->whereNull('annule_a')->where('date_fin', '>=', now());
    }
}
