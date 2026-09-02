<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\Boutique;
use App\Models\Paiement;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page d'atterrissage de l'espace /admin — accessible à tout administrateur actif
 * (EnsureAdminAccess), sans permission granulaire spécifique : ce ne sont que des
 * compteurs agrégés, pas des données individuelles sensibles.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'utilisateurs' => User::count(),
                'boutiques' => Boutique::count(),
                'boutiques_marketplace' => Boutique::eligiblesMarketplace()->count(),
                'abonnes_par_plan' => Abonnement::actuellementActif()
                    ->join('plans', 'plans.id', '=', 'abonnements.plan_id')
                    ->selectRaw('plans.code as plan, count(*) as total')
                    ->groupBy('plans.code')
                    ->pluck('total', 'plan'),
                'paiements_en_attente' => Paiement::where('statut', Paiement::STATUT_EN_ATTENTE)->count(),
                'paiements_approuves' => Paiement::where('statut', Paiement::STATUT_APPROUVE)->count(),
                'paiements_rejetes' => Paiement::where('statut', Paiement::STATUT_REJETE)->count(),
                'administrateurs_actifs' => User::where('role', User::ROLE_ADMIN)->where('est_actif', true)->count(),
            ],
        ]);
    }
}
