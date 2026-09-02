<?php

namespace App\Enums;

use App\Models\User;

enum FactureModele: int
{
    case Standard = 1;
    case Classique = 2;
    case Business = 3;
    case Modern = 4;
    case Premium = 5;
    case Corporate = 6;
    case Ecommerce = 7;
    case Elegant = 8;
    case Pro = 9;
    case Executive = 10;

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Classique => 'Classique',
            self::Business => 'Business',
            self::Modern => 'Modern',
            self::Premium => 'Premium',
            self::Corporate => 'Corporate',
            self::Ecommerce => 'E-commerce',
            self::Elegant => 'Élégant',
            self::Pro => 'Pro',
            self::Executive => 'Executive',
        };
    }

    /**
     * Modèles 1-2 : disponibles sur le plan Gratuit. Modèles 3-10 : réservés aux plans
     * dont modeles_facture_avances=true (Basique/Pro). Voir estAutorisePour().
     */
    public function estGratuit(): bool
    {
        return in_array($this, [self::Standard, self::Classique], true);
    }

    /**
     * Seule source de vérité pour "cet utilisateur peut-il utiliser ce modèle
     * MAINTENANT" — gouvernée par l'abonnement actuellement actif (jamais un snapshot
     * pris à la création d'une facture), même principe que Boutique::estEligibleMarketplace().
     */
    public function estAutorisePour(User $user): bool
    {
        return $this->estGratuit() || (bool) $user->planActif()?->modeles_facture_avances;
    }
}
