<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EssaiStatut;
use App\Http\Controllers\Controller;
use App\Models\EssaiUtilisateur;
use App\Models\ModeleNotificationEssai;
use App\Models\ParametreEssai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationEssaiController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAdminPermission('notifications.voir'), 403);

        return Inertia::render('Admin/Notifications/Index', [
            'modeles' => ModeleNotificationEssai::orderBy('jour')->get(),
            'parametres' => ParametreEssai::actuel(),
            'statistiques' => $this->statistiques(),
            'permissionsNotifications' => [
                'voir' => true,
                'envoyer' => $request->user()->hasAdminPermission('notifications.envoyer'),
            ],
        ]);
    }

    public function updateModele(Request $request, ModeleNotificationEssai $modele): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('notifications.envoyer'), 403);

        $data = $request->validate([
            'titre' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'actif' => ['required', 'boolean'],
        ]);

        $modele->update($data);

        return back()->with('flash_success', "Message du jour {$modele->jour} mis à jour.");
    }

    public function updateParametres(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('notifications.envoyer'), 403);

        $data = $request->validate([
            'heure_notification' => ['required', 'date_format:H:i'],
            'fuseau' => ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
        ]);

        ParametreEssai::actuel()->update($data);

        return back()->with('flash_success', "Heure d'envoi mise à jour.");
    }

    /**
     * Données réelles calculées depuis essais_utilisateurs — jamais de valeurs
     * fictives, conforme au cahier des charges.
     */
    private function statistiques(): array
    {
        $essais = EssaiUtilisateur::query()->get(['id', 'date_fin', 'converti_a', 'annule_a']);

        $actifs = 0;
        $expirantAujourdhui = 0;
        $expires = 0;
        $convertis = 0;

        foreach ($essais as $essai) {
            $statut = $essai->statut();

            if ($statut === EssaiStatut::Converti) {
                $convertis++;

                continue;
            }

            if ($statut === EssaiStatut::EnCours) {
                $actifs++;

                if ($essai->date_fin->isToday()) {
                    $expirantAujourdhui++;
                }

                continue;
            }

            if ($statut === EssaiStatut::Expire) {
                $expires++;
            }
        }

        return [
            'actifs' => $actifs,
            'expirant_aujourdhui' => $expirantAujourdhui,
            'expires' => $expires,
            'convertis' => $convertis,
        ];
    }
}
