<?php

namespace App\Services;

use App\Enums\TypeFacture;
use App\Models\Boutique;
use App\Models\Depense;
use App\Models\Facture;
use App\Models\MouvementCaisse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Caisse de chaque boutique -- seul point de création des mouvements de caisse.
 *
 * Grand livre : un mouvement n'est jamais modifié ni supprimé ; le solde est
 * toujours recalculé (Σ entrées − Σ sorties). Ventes et dépenses sont tenues à jour
 * par "synchronisation" : on calcule ce que le document DEVRAIT avoir apporté à la
 * caisse, on le compare à ce qui y a déjà été enregistré pour lui, et seul l'écart
 * est ajouté (entrée ou sortie de correction). Appeler la synchronisation deux fois
 * ne crée donc jamais de doublon.
 *
 * Ce qui compte comme argent reçu pour une vente (encaisseAttendu) :
 *  - Facture ou Reçu au statut "payée" : le total TTC ;
 *  - Reçu envoyé (paiement partiel) : l'avance versée (plafonnée au total) ;
 *  - brouillon, annulée, Proforma, facture envoyée non payée : rien.
 */
class CaisseService
{
    public function solde(int $boutiqueId): float
    {
        $totaux = MouvementCaisse::withoutGlobalScopes()
            ->where('boutique_id', $boutiqueId)
            ->selectRaw("COALESCE(SUM(CASE WHEN sens = 'entree' THEN montant ELSE 0 END), 0) as entrees")
            ->selectRaw("COALESCE(SUM(CASE WHEN sens = 'sortie' THEN montant ELSE 0 END), 0) as sorties")
            ->first();

        return round((float) $totaux->entrees - (float) $totaux->sorties, 2);
    }

    /** Ajout d'argent dans la caisse (fonds de caisse, apport...). */
    public function apport(Boutique $boutique, User $user, float $montant, string $motif): MouvementCaisse
    {
        return $this->enregistrer($boutique->id, MouvementCaisse::ENTREE, 'apport', $montant, $motif, userId: $user->id);
    }

    /**
     * Retrait d'argent de la caisse. Refusé s'il dépasse le solde : verrou sur la
     * boutique pour que deux retraits simultanés ne puissent pas tous deux passer.
     */
    public function retrait(Boutique $boutique, User $user, float $montant, string $motif): MouvementCaisse
    {
        return DB::transaction(function () use ($boutique, $user, $montant, $motif) {
            Boutique::whereKey($boutique->id)->lockForUpdate()->first();

            $solde = $this->solde($boutique->id);
            if ($montant > $solde) {
                throw ValidationException::withMessages([
                    'montant' => 'Montant supérieur au solde de la caisse ('.number_format($solde, 0, ',', ' ')." {$boutique->devise}).",
                ]);
            }

            return $this->enregistrer($boutique->id, MouvementCaisse::SORTIE, 'retrait', $montant, $motif, userId: $user->id);
        });
    }

    /**
     * À appeler après toute création/modification/changement de statut d'une
     * facture, et AVANT sa suppression ($supprimee = true).
     */
    public function synchroniserFacture(Facture $facture, ?int $userId, bool $supprimee = false): void
    {
        $boutique = Boutique::find($facture->boutique_id);
        if (! $boutique || ! $this->concerne($facture->created_at, $boutique)) {
            return;
        }

        $attendu = $supprimee ? 0.0 : $this->encaisseAttendu($facture);
        $enregistre = $this->net('facture_id', $facture->id, MouvementCaisse::ENTREE);
        $ecart = round($attendu - $enregistre, 2);

        if ($ecart == 0.0) {
            return;
        }

        if ($ecart > 0) {
            $libelle = $facture->type === TypeFacture::Recu && $facture->statut !== 'payee'
                ? "Avance sur reçu {$facture->numero}"
                : ($enregistre > 0 ? "Complément vente {$facture->numero}" : "Vente {$facture->numero}");
            $this->enregistrer($boutique->id, MouvementCaisse::ENTREE, 'vente', $ecart, $libelle, factureId: $facture->id, userId: $userId);

            return;
        }

        $raison = match (true) {
            $supprimee => 'supprimée',
            $facture->statut === 'annulee' => 'annulée',
            $facture->statut === 'brouillon' => 'repassée en brouillon',
            $facture->statut === 'envoyee' && $facture->type !== TypeFacture::Recu => 'repassée en impayée',
            default => 'modifiée',
        };
        $this->enregistrer($boutique->id, MouvementCaisse::SORTIE, 'vente', -$ecart, "Correction vente {$facture->numero} ({$raison})", factureId: $facture->id, userId: $userId);
    }

    /** Branché sur les événements du modèle Depense (création, modification, suppression). */
    public function synchroniserDepense(Depense $depense, bool $supprimee = false): void
    {
        $boutique = Boutique::find($depense->boutique_id);
        if (! $boutique || ! $this->concerne($depense->created_at, $boutique)) {
            return;
        }

        $attendu = $supprimee ? 0.0 : (float) $depense->montant;
        $enregistre = $this->net('depense_id', $depense->id, MouvementCaisse::SORTIE);
        $ecart = round($attendu - $enregistre, 2);

        if ($ecart == 0.0) {
            return;
        }

        $nom = 'Dépense : '.$depense->categorie.($depense->description ? ' — '.Str::limit($depense->description, 80) : '');
        $userId = auth()->id() ?? $depense->created_by;

        if ($ecart > 0) {
            $this->enregistrer($boutique->id, MouvementCaisse::SORTIE, 'depense', $ecart, $enregistre > 0 ? "{$nom} (montant augmenté)" : $nom, depenseId: $depense->id, userId: $userId);

            return;
        }

        $this->enregistrer($boutique->id, MouvementCaisse::ENTREE, 'depense', -$ecart, "Correction {$nom} (".($supprimee ? 'supprimée' : 'montant réduit').')', depenseId: $depense->id, userId: $userId);
    }

    private function encaisseAttendu(Facture $facture): float
    {
        if ($facture->estProforma() || in_array($facture->statut, ['brouillon', 'annulee'], true)) {
            return 0.0;
        }

        if ($facture->statut === 'payee') {
            return max(0.0, (float) $facture->total_ttc);
        }

        if ($facture->type === TypeFacture::Recu) {
            return max(0.0, min((float) ($facture->montant_avance ?? 0), (float) $facture->total_ttc));
        }

        return 0.0;
    }

    /**
     * Montant net déjà passé en caisse pour un document, dans son sens "normal"
     * (entrées d'une vente, sorties d'une dépense), corrections déduites.
     */
    private function net(string $colonne, int $id, string $sensNormal): float
    {
        $lignes = MouvementCaisse::withoutGlobalScopes()->where($colonne, $id)->get(['sens', 'montant']);

        return round($lignes->sum(fn ($m) => $m->sens === $sensNormal ? (float) $m->montant : -(float) $m->montant), 2);
    }

    private function concerne($creeLe, Boutique $boutique): bool
    {
        return $creeLe === null || $creeLe->greaterThanOrEqualTo($boutique->caisseOuverteLe());
    }

    private function enregistrer(int $boutiqueId, string $sens, string $origine, float $montant, string $libelle, ?int $factureId = null, ?int $depenseId = null, ?int $userId = null): MouvementCaisse
    {
        return MouvementCaisse::withoutGlobalScopes()->create([
            'boutique_id' => $boutiqueId,
            'sens' => $sens,
            'origine' => $origine,
            'montant' => round($montant, 2),
            'libelle' => Str::limit($libelle, 250),
            'facture_id' => $factureId,
            'depense_id' => $depenseId,
            'user_id' => $userId,
        ]);
    }
}
