<?php

namespace App\Policies;

use App\Enums\FactureModele;
use App\Models\Facture;
use App\Models\User;

class FacturePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function view(User $user, Facture $facture): bool
    {
        return $user->id === $facture->boutique->user_id;
    }

    public function create(User $user): bool
    {
        return $user->currentBoutique !== null;
    }

    public function update(User $user, Facture $facture): bool
    {
        return $user->id === $facture->boutique->user_id;
    }

    public function delete(User $user, Facture $facture): bool
    {
        return $user->id === $facture->boutique->user_id && $facture->estModifiable();
    }

    /**
     * Seul point de vérité pour "cet utilisateur peut-il utiliser ce modele_id", utilisé
     * à la fois à la création (StoreFactureRequest, $facture=null) et à la modification
     * (UpdateFactureRequest). Ne bloque jamais un modele_id déjà enregistré sur la facture
     * (aucune régression rétroactive quand le plan change) — seul un CHANGEMENT vers un
     * nouveau modèle est soumis à l'abonnement actuellement actif.
     */
    public function useModele(User $user, int $modeleId, ?Facture $facture = null): bool
    {
        if ($facture && $facture->modele_id?->value === $modeleId) {
            return true;
        }

        $modele = FactureModele::tryFrom($modeleId);

        // Un id invalide n'est pas une question d'autorisation : la validation (rules())
        // le rejettera proprement en 422. Ne pas le transformer ici en 403.
        if (! $modele) {
            return true;
        }

        return $modele->estAutorisePour($user);
    }
}
