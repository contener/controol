<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Produit;

class MessageTemplateService
{
    public function rendre(string $message, Boutique $boutique, ?Produit $produit = null): string
    {
        $remplacements = [
            '{{shop_name}}' => $boutique->nom,
            '{{shop_url}}' => route('public.boutique', $boutique->slug),
            '{{product_name}}' => $produit?->nom ?? '',
            '{{product_price}}' => $produit ? number_format((float) $produit->prixAffiche(), 0, ',', ' ').' '.$boutique->devise : '',
        ];

        return str_replace(array_keys($remplacements), array_values($remplacements), $message);
    }
}
