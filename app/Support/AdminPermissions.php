<?php

namespace App\Support;

/**
 * Catalogue centralisé des permissions administrateur — désigné en code, pas en base
 * (ce sont des clés fonctionnelles figées, pas des données éditables). Deux usages :
 * 1. rendre la grille de cases à cocher du formulaire Administrateurs (groupes());
 * 2. whitelister les clés acceptées par StoreAdministrateurRequest/UpdateAdministrateurRequest
 *    (toutesLesCles(), via Rule::in()) — empêche l'injection d'une clé arbitraire.
 *
 * Certaines clés ne sont pas encore vérifiées par un middleware réel (aucune page de
 * gestion des utilisateurs finaux ou des boutiques par un admin n'existe encore) : elles
 * restent dans le catalogue pour correspondre au cahier des charges, mais cocher ces
 * cases n'accorde aujourd'hui aucun accès concret. Voir CLES_ACTIVES ci-dessous.
 */
class AdminPermissions
{
    public const GROUPES = [
        'utilisateurs' => [
            'label' => 'Gestion utilisateurs',
            'permissions' => [
                'utilisateurs.voir' => 'Voir',
                'utilisateurs.creer' => 'Créer',
                'utilisateurs.modifier' => 'Modifier',
                'utilisateurs.suspendre' => 'Suspendre',
                'utilisateurs.supprimer' => 'Supprimer',
            ],
        ],
        'boutiques' => [
            'label' => 'Gestion boutiques',
            'permissions' => [
                'boutiques.voir' => 'Voir',
                'boutiques.modifier' => 'Modifier',
                'boutiques.suspendre' => 'Suspendre',
                'boutiques.supprimer' => 'Supprimer',
            ],
        ],
        'paiements' => [
            'label' => 'Gestion paiements',
            'permissions' => [
                'paiements.voir' => 'Voir',
                'paiements.valider' => 'Valider',
                'paiements.refuser' => 'Refuser',
            ],
        ],
        'marketplace' => [
            'label' => 'Marketplace',
            'permissions' => [
                'marketplace.voir' => 'Voir',
                'marketplace.modifier' => 'Modifier',
                'marketplace.suspendre' => 'Suspendre',
            ],
        ],
        'abonnements' => [
            'label' => 'Abonnements',
            'permissions' => [
                'abonnements.voir' => 'Voir',
                'abonnements.modifier' => 'Modifier',
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'permissions' => [
                'notifications.voir' => 'Voir',
                'notifications.envoyer' => 'Envoyer',
            ],
        ],
        'whatsapp' => [
            'label' => 'Relance WhatsApp',
            'permissions' => [
                'whatsapp.voir' => 'Voir les numéros',
                'whatsapp.contacter' => 'Relancer un utilisateur',
                'whatsapp.historique' => 'Voir l\'historique des relances',
            ],
        ],
    ];

    /**
     * Clés effectivement vérifiées par un middleware admin.permission:<cle> aujourd'hui.
     * Le reste du catalogue est réservé pour de futures sections (gestion générale des
     * boutiques indépendamment d'un utilisateur, abonnements, notifications).
     */
    public const CLES_ACTIVES = [
        'paiements.voir',
        'paiements.valider',
        'paiements.refuser',
        'marketplace.voir',
        'marketplace.suspendre',
        'utilisateurs.voir',
        'utilisateurs.suspendre',
        'utilisateurs.supprimer',
        'whatsapp.voir',
        'whatsapp.contacter',
        'whatsapp.historique',
    ];

    public const PRESETS_PAR_ROLE = [
        'ADMIN_PAIEMENTS' => ['paiements.voir', 'paiements.valider', 'paiements.refuser', 'utilisateurs.voir', 'whatsapp.voir', 'whatsapp.contacter'],
        'ADMIN_UTILISATEURS' => ['utilisateurs.voir', 'utilisateurs.creer', 'utilisateurs.modifier', 'utilisateurs.suspendre'],
        'ADMIN_BOUTIQUES' => ['boutiques.voir', 'boutiques.modifier', 'boutiques.suspendre'],
        'ADMIN_MARKETPLACE' => ['marketplace.voir', 'marketplace.modifier', 'marketplace.suspendre'],
        'ADMIN_SUPPORT' => ['utilisateurs.voir', 'boutiques.voir', 'paiements.voir', 'whatsapp.voir', 'whatsapp.contacter', 'whatsapp.historique'],
        'ADMIN' => [],
    ];

    public static function toutesLesCles(): array
    {
        return collect(self::GROUPES)
            ->flatMap(fn (array $groupe) => array_keys($groupe['permissions']))
            ->values()
            ->all();
    }

    public static function libellesRoles(): array
    {
        return array_keys(self::PRESETS_PAR_ROLE);
    }

    public static function presetPour(?string $roleLabel): array
    {
        return self::PRESETS_PAR_ROLE[$roleLabel] ?? [];
    }
}
