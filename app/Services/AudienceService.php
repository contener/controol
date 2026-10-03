<?php

namespace App\Services;

use App\Models\AudienceInteraction;
use App\Models\AudienceMembre;
use App\Models\Boutique;
use App\Models\Produit;

/**
 * Point d'entrée unique pour journaliser une interaction visiteur/produit, quelle que
 * soit son origine (like public, message existant via les conversations...) -- jamais
 * de duplication de la logique "trouver ou créer le membre d'audience" entre les
 * différents points de branchement.
 */
class AudienceService
{
    /**
     * @param  array{user_id?: int}|array{visiteur_token?: string}  $identite  Exactement une des deux clés.
     */
    public function enregistrerInteraction(
        Boutique $boutique,
        string $type,
        ?Produit $produit,
        array $identite,
        ?string $nom = null,
        ?string $contact = null,
    ): AudienceInteraction {
        // withoutGlobalScopes() : cette méthode est appelée depuis un contexte public
        // non authentifié (aimerProduit, envoyerMessage) où le visiteur -- s'il est
        // connecté -- peut lui-même posséder une autre boutique. Le scope
        // BelongsToBoutique filtrerait alors par SA boutique courante, pas celle visitée
        // ici. boutique_id est toujours explicitement fourni ci-dessous, jamais déduit.
        $membre = AudienceMembre::withoutGlobalScopes()->firstOrCreate([
            'boutique_id' => $boutique->id,
            ...$identite,
        ]);

        // Ne renseigne nom/contact que pour un visiteur anonyme qui ne les a pas déjà
        // -- on garde la première information fiable, jamais écrasée par une
        // interaction ultérieure moins complète (ex. un like sans nom après un message
        // qui en avait un).
        if ($membre->estAnonyme()) {
            $miseAJour = [];
            if ($nom && ! $membre->nom) {
                $miseAJour['nom'] = $nom;
            }
            if ($contact && ! $membre->contact) {
                $miseAJour['contact'] = $contact;
            }
            if ($miseAJour !== []) {
                $membre->update($miseAJour);
            }
        }

        $donnees = [
            'boutique_id' => $boutique->id,
            'audience_membre_id' => $membre->id,
            'produit_id' => $produit?->id,
            'type' => $type,
            'created_at' => now(),
        ];

        // Un like est un événement à sens unique (pas de "unlike" en v1) : idempotent,
        // jamais de doublon même si le visiteur clique plusieurs fois sur le même
        // produit. Tout autre type (message...) est un événement réel distinct à
        // chaque occurrence.
        if ($type === AudienceInteraction::LIKE) {
            return AudienceInteraction::withoutGlobalScopes()->firstOrCreate([
                'audience_membre_id' => $membre->id,
                'produit_id' => $produit?->id,
                'type' => $type,
            ], $donnees);
        }

        return AudienceInteraction::withoutGlobalScopes()->create($donnees);
    }
}
