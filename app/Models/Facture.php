<?php

namespace App\Models;

use App\Enums\FactureModele;
use App\Enums\TypeFacture;
use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facture extends Model
{
    use BelongsToBoutique, HasFactory;

    protected $fillable = [
        'boutique_id',
        'client_id',
        'type',
        'numero',
        'statut',
        'modele_id',
        'date_emission',
        'date_echeance',
        'sous_total',
        'remise',
        'total_tva',
        'total_ttc',
        'montant_avance',
        'notes',
        'garantie',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeFacture::class,
            'modele_id' => FactureModele::class,
            'date_emission' => 'date',
            'date_echeance' => 'date',
            'sous_total' => 'decimal:2',
            'remise' => 'decimal:2',
            'total_tva' => 'decimal:2',
            'total_ttc' => 'decimal:2',
            'montant_avance' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(FactureLigne::class)->orderBy('ordre');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    /**
     * Une facture Annulée reste verrouillée (la rouvrir n'aurait pas de sens) ; tous
     * les autres statuts (brouillon/envoyée/payée) sont modifiables -- corriger une
     * erreur après envoi ou paiement reste possible, à la différence du numéro et du
     * type (Facture/Proforma), toujours immuables une fois la facture créée.
     */
    public function estModifiable(): bool
    {
        return $this->statut !== 'annulee';
    }

    /**
     * Suppression manuelle disponible pour tous les statuts (y compris Payée) dès
     * qu'au moins 14 jours se sont écoulés depuis la création -- laisse le temps de
     * repérer une erreur/un doublon avant que la facture ne devienne définitive,
     * sans jamais permettre un nettoyage impulsif du jour même.
     */
    public function estSupprimable(): bool
    {
        return $this->created_at !== null && $this->created_at->lte(now()->subDays(14));
    }

    /**
     * Un proforma est un document indicatif, jamais un engagement de vente — il ne doit
     * jamais faire bouger le stock (voir FactureService), contrairement à une facture.
     */
    public function estProforma(): bool
    {
        return $this->type === TypeFacture::Proforma;
    }

    /**
     * Propre aux Reçus : jamais stocké (même principe que partout ailleurs dans ce
     * projet pour une donnée dérivable -- EssaiUtilisateur::statut(), Suivi::estActif()...),
     * toujours recalculé à la volée depuis le total et l'avance versée. Ne descend
     * jamais sous zéro (une avance saisie par erreur au-delà du total ne doit jamais
     * afficher un "reste" négatif).
     */
    public function resteAPayer(): float
    {
        return max(0.0, (float) $this->total_ttc - (float) ($this->montant_avance ?? 0));
    }
}
