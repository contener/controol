<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Passage par la page de paiement CamerPay pour un Paiement d'abonnement -- créée et
 * mise à jour uniquement par PaiementEnLigneService. Aucun champ n'est rempli depuis
 * une requête : $fillable ne sert qu'aux créations internes du service.
 */
class TentativePaiement extends Model
{
    public const EN_COURS = 'en_cours';

    public const CONFIRMEE = 'confirmee';

    public const ECHOUEE = 'echouee';

    protected $table = 'tentatives_paiement';

    protected $fillable = [
        'paiement_id',
        'user_id',
        'prestataire',
        'reference_marchand',
        'reference_prestataire',
        'montant',
        'devise',
        'statut',
        'statut_prestataire',
        'empreinte_notification',
        'verifiee_le',
        'confirmee_le',
    ];

    protected $hidden = ['empreinte_notification'];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'verifiee_le' => 'datetime',
            'confirmee_le' => 'datetime',
        ];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function estTerminee(): bool
    {
        return $this->statut !== self::EN_COURS;
    }
}
