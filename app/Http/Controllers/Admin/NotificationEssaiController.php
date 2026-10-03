<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EssaiStatut;
use App\Http\Controllers\Controller;
use App\Models\EssaiUtilisateur;
use App\Models\ModeleNotificationEssai;
use App\Models\ParametreEssai;
use App\Support\WhatsappModeles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'essais' => $this->essaisUtilisateurs($request),
            'filtresEssais' => $request->only(['statutEssai', 'rechercheEssai']),
            'permissionsWhatsapp' => [
                'voir' => $request->user()->hasAdminPermission('whatsapp.voir'),
                'contacter' => $request->user()->hasAdminPermission('whatsapp.contacter'),
            ],
            'modelesWhatsappEssai' => collect(WhatsappModeles::liste())
                ->whereIn('cle', [WhatsappModeles::ESSAI_RELANCE_SANS_BOUTIQUE, WhatsappModeles::ESSAI_RELANCE_AVEC_BOUTIQUE])
                ->values()
                ->all(),
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
     * Liste individuelle (pas seulement des agrégats) : statut calculé, jours restants,
     * présence d'une boutique -- tout ce qu'il faut pour relancer un utilisateur d'essai
     * au bon moment, avec le bon message. Le statut n'étant pas une colonne persistée
     * (EssaiUtilisateur::statut()), le filtre traduit chaque valeur en conditions SQL
     * équivalentes plutôt que de filtrer après coup (ce qui casserait la pagination).
     */
    private function essaisUtilisateurs(Request $request): LengthAwarePaginator
    {
        $statutFiltre = $request->string('statutEssai')->toString();
        $recherche = $request->string('rechercheEssai')->toString();

        $query = EssaiUtilisateur::query()->with(['user' => fn ($q) => $q->withCount('boutiques')]);

        match ($statutFiltre) {
            'en_cours' => $query->whereNull('converti_a')->whereNull('annule_a')->where('date_fin', '>=', now()),
            'expire' => $query->whereNull('converti_a')->whereNull('annule_a')->where('date_fin', '<', now()),
            'converti' => $query->whereNotNull('converti_a'),
            'annule' => $query->whereNotNull('annule_a'),
            default => null,
        };

        if ($recherche !== '') {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%"));
        }

        $essais = $query->orderByDesc('date_debut')->paginate(15)->withQueryString();

        $voirWhatsapp = $request->user()->hasAdminPermission('whatsapp.voir');

        $essais->getCollection()->transform(fn (EssaiUtilisateur $essai) => $this->formaterEssai($essai, $voirWhatsapp));

        return $essais;
    }

    private function formaterEssai(EssaiUtilisateur $essai, bool $voirWhatsapp): array
    {
        $statut = $essai->statut();

        return [
            'id' => $essai->id,
            'user_id' => $essai->user_id,
            'nom' => $essai->user->name,
            'email' => $essai->user->email,
            'whatsapp' => $voirWhatsapp ? $essai->user->numeroWhatsapp() : null,
            'a_boutique' => $essai->user->boutiques_count > 0,
            'statut' => $statut->value,
            'statut_label' => $statut->label(),
            'jour_actuel' => $essai->jourActuel(),
            'jours_restants' => $statut === EssaiStatut::EnCours ? $essai->joursRestants() : 0,
            'dernier_jour_notifie' => $essai->dernier_jour_notifie,
            'date_debut' => $essai->date_debut->format('d/m/Y'),
            'date_fin' => $essai->date_fin->format('d/m/Y'),
        ];
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
