<?php

namespace App\Services;

use App\Models\Produit;
use Illuminate\Support\Facades\DB;

/**
 * Notifie les abonnés d'une boutique de la publication d'un produit -- extrait de
 * ProduitController (web) pour être partagé avec Api\ProduitController (app mobile) :
 * publier un produit depuis le téléphone doit prévenir les abonnés exactement comme
 * depuis le site, jamais une seconde version du message.
 */
class NotificationAbonnesService
{
    public function nouveauProduit(Produit $produit): void
    {
        $destinataires = DB::table('suivis_boutique')
            ->where('boutique_id', $produit->boutique_id)
            ->where('notifications_actives', true)
            ->whereNull('desabonne_a')
            ->pluck('user_id');

        if ($destinataires->isEmpty()) {
            return;
        }

        $boutique = $produit->boutique;
        $prix = $produit->promotion_prix ?? $produit->prix_vente;

        $message = "{$boutique->nom} vient de publier « {$produit->nom} »";
        if ($produit->mini_characteristics) {
            $message .= " — {$produit->mini_characteristics}";
        }
        $message .= ' à '.number_format((float) $prix, 0, ',', ' ')." {$boutique->devise}.";

        // URL absolue construite ici, une seule fois, plutôt que devinée côté client à
        // partir d'un slug -- reste valide même si la structure des routes change.
        $lien = route('public.boutique', $boutique->slug);
        $maintenant = now();

        DB::table('notifications_utilisateurs')->insert(
            $destinataires->map(fn ($userId) => [
                'user_id' => $userId,
                'type' => 'nouveau_produit',
                'titre' => "Nouveau produit chez {$boutique->nom}",
                'message' => $message,
                'lien' => $lien,
                'est_promotionnelle' => false,
                'created_at' => $maintenant,
            ])->all()
        );
    }
}
