<?php

namespace App\Support;

use App\Models\Paiement;
use App\Models\TentativePaiement;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Historique des paiements d'abonnement vu par l'utilisateur (site et application),
 * 2026-11-24.
 *
 * Sécurité :
 *  - toujours filtré sur user_id = l'utilisateur connecté (aucun identifiant reçu
 *    de la requête ne sert à choisir les paiements) ;
 *  - liste blanche explicite des champs renvoyés : jamais un modèle sérialisé tel
 *    quel. Ne sortent JAMAIS : l'administrateur qui a validé (valide_par), le journal
 *    d'audit (admin, adresse IP), les empreintes de notification, les identifiants
 *    internes, ni les références complètes (marchand et CamerPay), seulement masquées
 *    (6 derniers caractères) pour pouvoir les citer au support ;
 *  - le statut affiché est dérivé (jamais stocké en double), voir statut().
 */
class HistoriquePaiements
{
    public const PAR_PAGE = 15;

    public static function pour(User $user, int $page = 1): LengthAwarePaginator
    {
        return Paiement::query()
            ->where('user_id', $user->id)
            ->with([
                'abonnement:id,plan_id,statut,date_debut,date_fin',
                'abonnement.plan:id,nom',
                'tentatives' => fn ($q) => $q->latest('id')->limit(10),
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PAR_PAGE, ['*'], 'page', max(1, $page))
            ->through(fn (Paiement $p) => self::ligne($p));
    }

    /** @return array<string, mixed> */
    public static function ligne(Paiement $paiement): array
    {
        $derniere = $paiement->tentatives->first();
        $statut = self::statut($paiement, $derniere);

        return [
            'plan' => $paiement->abonnement?->plan?->nom,
            'montant' => (float) $paiement->montant,
            'devise' => $paiement->devise,
            'moyen' => $paiement->moyen_paiement === 'camerpay' ? 'Paiement en ligne (CamerPay)' : 'Lien de paiement',
            'statut' => $statut,
            'statut_libelle' => self::LIBELLES[$statut],
            // Motif saisi par l'administrateur à l'intention de l'utilisateur lui-même.
            'motif_rejet' => $paiement->statut === Paiement::STATUT_REJETE ? $paiement->motif_rejet : null,
            'reference' => self::masquer($paiement->reference_transaction ?? $derniere?->reference_marchand),
            'cree_le' => $paiement->created_at?->toIso8601String(),
            'valide_le' => $paiement->valide_at?->toIso8601String(),
            'abonnement_jusqu_au' => $paiement->statut === Paiement::STATUT_APPROUVE ? $paiement->abonnement?->date_fin?->toIso8601String() : null,
            'tentatives' => $paiement->tentatives->map(fn (TentativePaiement $t) => [
                'statut' => $t->statut,
                'statut_libelle' => self::LIBELLES_TENTATIVE[$t->statut] ?? 'En cours',
                'reference' => self::masquer($t->reference_marchand),
                'le' => $t->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public const LIBELLES = [
        'paye' => 'Payé',
        'refuse' => 'Refusé',
        'en_cours' => 'Paiement en cours',
        'echoue' => 'Échoué',
        'en_attente' => 'En attente de validation',
    ];

    private const LIBELLES_TENTATIVE = [
        TentativePaiement::EN_COURS => 'En cours',
        TentativePaiement::CONFIRMEE => 'Confirmé',
        TentativePaiement::ECHOUEE => 'Échoué',
    ];

    /**
     * paye / refuse : décision finale sur le Paiement ; sinon, état de la dernière
     * tentative en ligne (en_cours / echoue) ou, sans tentative, attente de validation
     * manuelle (ancien parcours par lien de paiement).
     */
    private static function statut(Paiement $paiement, ?TentativePaiement $derniere): string
    {
        return match (true) {
            $paiement->statut === Paiement::STATUT_APPROUVE => 'paye',
            $paiement->statut === Paiement::STATUT_REJETE => 'refuse',
            $derniere?->statut === TentativePaiement::ECHOUEE => 'echoue',
            $derniere !== null => 'en_cours',
            default => 'en_attente',
        };
    }

    private static function masquer(?string $reference): ?string
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        return '…'.substr($reference, -6);
    }
}
