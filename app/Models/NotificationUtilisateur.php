<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationUtilisateur extends Model
{
    protected $table = 'notifications_utilisateurs';

    // La table n'a pas de colonne updated_at (une notification n'est jamais modifiée
    // après création, seulement marquée lue via lu_a) — Eloquent gère created_at seul.
    const UPDATED_AT = null;

    /**
     * Regroupement des types pour les filtres de l'écran Notifications (app mobile).
     * Tout type absent d'ici (essai, parrainage, annonces de l'administration...)
     * tombe dans "autres" -- jamais de type perdu si un nouveau est ajouté plus tard.
     */
    public const CATEGORIES = [
        'messages' => ['message_recu', 'message_vendeur'],
        'jaime' => ['like'],
        'abonnes' => ['nouvel_abonne'],
        'commandes' => ['nouvelle_commande'],
        'boutiques' => ['nouvelle_boutique', 'boutique_creee', 'nouveau_produit', 'collaborateur_ajoute'],
        'abonnement' => ['paiement_approuve', 'paiement_rejete'],
    ];

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'lien',
        'donnees',
        'est_promotionnelle',
        'lu_a',
    ];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
            'est_promotionnelle' => 'boolean',
            'lu_a' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marquerLu(): void
    {
        if ($this->lu_a === null) {
            $this->update(['lu_a' => now()]);
        }
    }
}
