<?php

namespace App\Enums;

enum TypeFacture: string
{
    case Facture = 'facture';
    case Proforma = 'proforma';

    public function label(): string
    {
        return match ($this) {
            self::Facture => 'Facture',
            self::Proforma => 'Proforma',
        };
    }

    /**
     * Préfixe de numérotation — chaque type a sa propre séquence (voir
     * FactureNumeroService/CompteurFacture) : un proforma ne doit jamais consommer un
     * numéro de la séquence légale des factures, même s'il n'est jamais transformé.
     */
    public function prefixeNumero(): string
    {
        return match ($this) {
            self::Facture => 'FAC',
            self::Proforma => 'PRO',
        };
    }
}
