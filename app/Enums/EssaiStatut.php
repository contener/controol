<?php

namespace App\Enums;

enum EssaiStatut: string
{
    case EnCours = 'en_cours';
    case Converti = 'converti';
    case Expire = 'expire';
    case Annule = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::EnCours => 'Essai en cours',
            self::Converti => 'Converti',
            self::Expire => 'Essai expiré',
            self::Annule => 'Annulé',
        };
    }
}
