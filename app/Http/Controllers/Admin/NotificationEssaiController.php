<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EssaiStatut;
use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\AdminAudit;
use App\Models\EssaiUtilisateur;
use App\Models\ModeleNotificationEssai;
use App\Models\NotificationUtilisateur;
use App\Models\ParametreEssai;
use App\Models\Plan;
use App\Models\WhatsappContactLog;
use App\Support\WhatsappModeles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            'relancesAutomatiques' => $this->relancesAutomatiques(),
            'permissionsNotifications' => [
                'voir' => true,
                'envoyer' => $request->user()->hasAdminPermission('notifications.envoyer'),
            ],
            'essais' => $this->essaisUtilisateurs($request),
            'filtresEssais' => $request->only(['statutEssai', 'rechercheEssai']),
            'permissionsWhatsapp' => [
                'voir' => $request->user()->hasAdminPermission('whatsapp.voir'),
                'contacter' => $request->user()->hasAdminPermission('whatsapp.contacter'),
                'historique' => $request->user()->hasAdminPermission('whatsapp.historique'),
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
     * Redonne 7 jours d'essai à un utilisateur dont l'essai est expiré -- jamais de
     * mutation de la ligne existante (principe "grand livre" déjà suivi pour le
     * parrainage/les paiements, voir §3 de controol.md) : nouvel Abonnement + nouvel
     * EssaiUtilisateur, comme une inscription normale (NouvelUtilisateurService), pour
     * que l'historique du premier essai (date de fin réelle, jamais réécrite) reste
     * consultable. Un essai déjà en cours, converti ou annulé ne peut pas être
     * "réactivé" -- action réservée au seul cas où l'essai est bien arrivé à expiration.
     */
    public function reactiverEssai(Request $request, EssaiUtilisateur $essai): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('notifications.envoyer'), 403);
        abort_unless($essai->statut() === EssaiStatut::Expire, 422, "Seul un essai expiré peut être réactivé.");

        $planBasique = Plan::where('code', 'basique')->first();
        abort_unless($planBasique, 422, "Le plan Basique n'existe pas.");

        $dateDebut = now();
        $dateFin = $dateDebut->copy()->addDays(7);

        DB::transaction(function () use ($essai, $planBasique, $dateDebut, $dateFin, $request) {
            $abonnement = Abonnement::create([
                'user_id' => $essai->user_id,
                'plan_id' => $planBasique->id,
                'statut' => 'actif',
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ]);

            EssaiUtilisateur::create([
                'user_id' => $essai->user_id,
                'abonnement_id' => $abonnement->id,
                'plan_id' => $planBasique->id,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'prix_promo' => 3500,
            ]);

            AdminAudit::create([
                'admin_id' => $request->user()->id,
                'action' => 'essai_reactive',
                'resource' => 'essai_utilisateur',
                'resource_id' => $essai->id,
                'ancienne_valeur' => ['statut' => 'expire', 'date_fin' => $essai->date_fin->toDateTimeString()],
                'nouvelle_valeur' => ['date_debut' => $dateDebut->toDateTimeString(), 'date_fin' => $dateFin->toDateTimeString()],
                'ip_address' => $request->ip(),
            ]);

            NotificationUtilisateur::create([
                'user_id' => $essai->user_id,
                'type' => 'essai_rappel',
                'titre' => 'Votre essai gratuit a été réactivé !',
                'message' => "Bonne nouvelle : votre essai gratuit du plan Basique a été réactivé pour 7 jours supplémentaires. Profitez-en pour découvrir toutes les fonctionnalités et faire votre choix d'abonnement en toute tranquillité.",
                'est_promotionnelle' => true,
                'created_at' => now(),
            ]);
        });

        return back()->with('flash_success', "Essai réactivé pour 7 jours supplémentaires — l'utilisateur a été notifié.");
    }

    /**
     * Liste individuelle (pas seulement des agrégats) : statut calculé, jours restants,
     * présence d'une boutique -- tout ce qu'il faut pour relancer un utilisateur d'essai
     * au bon moment, avec le bon message. Le statut n'étant pas une colonne persistée
     * (EssaiUtilisateur::statut()), le filtre traduit chaque valeur en conditions SQL
     * équivalentes plutôt que de filtrer après coup (ce qui casserait la pagination).
     *
     * Un seul essai par utilisateur affiché : le plus récent (id le plus élevé). Depuis
     * reactiverEssai(), un utilisateur peut avoir plusieurs lignes essais_utilisateurs
     * (l'ancienne expirée jamais réécrite + la nouvelle) -- sans ce filtre, il
     * apparaîtrait deux fois (une fois "Expiré", une fois "En cours"), et la ligne
     * "Expiré" resterait affichée telle quelle après réactivation, donnant
     * l'impression que l'action n'a rien fait alors qu'elle a bien fonctionné.
     */
    private function essaisUtilisateurs(Request $request): LengthAwarePaginator
    {
        $statutFiltre = $request->string('statutEssai')->toString();
        $recherche = $request->string('rechercheEssai')->toString();

        $dernierEssaiParUtilisateur = EssaiUtilisateur::selectRaw('MAX(id) as id')->groupBy('user_id');

        $query = EssaiUtilisateur::query()
            ->whereIn('id', $dernierEssaiParUtilisateur)
            ->with(['user' => fn ($q) => $q->withCount('boutiques')]);

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
        $voirHistorique = $request->user()->hasAdminPermission('whatsapp.historique');
        $dernieresRelances = $voirHistorique
            ? $this->dernieresRelancesParUtilisateur($essais->getCollection()->pluck('user_id'))
            : collect();

        $essais->getCollection()->transform(fn (EssaiUtilisateur $essai) => $this->formaterEssai($essai, $voirWhatsapp, $dernieresRelances->get($essai->user_id)));

        return $essais;
    }

    /**
     * Une seule requête pour toute la page (pas N+1) -- ne garde que la relance la plus
     * récente par utilisateur, confirmée ou non, tous modèles de message confondus
     * (pas seulement les deux modèles d'essai : une relance envoyée via un autre modèle
     * reste une preuve valable de contact récent).
     */
    private function dernieresRelancesParUtilisateur(Collection $userIds): Collection
    {
        return WhatsappContactLog::whereIn('user_id', $userIds)
            ->whereNotNull('user_id')
            ->with('admin:id,name')
            ->orderByDesc('ouvert_a')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');
    }

    private function formaterEssai(EssaiUtilisateur $essai, bool $voirWhatsapp, ?WhatsappContactLog $derniereRelance): array
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
            'derniere_relance' => $derniereRelance ? [
                'id' => $derniereRelance->id,
                'ouvert_a' => $derniereRelance->ouvert_a->format('d/m/Y H:i'),
                'confirme' => $derniereRelance->confirme,
                'admin_nom' => $derniereRelance->admin?->name,
            ] : null,
        ];
    }

    /**
     * Confirmation visible que la tâche planifiée quotidienne (`essais:notifier`, voir
     * routes/console.php) s'exécute réellement chaque matin -- pas seulement qu'elle
     * est configurée. Historique de 14 jours plutôt qu'un seul chiffre "aujourd'hui" :
     * un vrai incident (scheduler non configuré, déjà rencontré une fois sur ce projet,
     * voir §5.17/historique du 2026-10-04) se voit immédiatement comme un ou plusieurs
     * jours à 0, bien avant qu'un utilisateur ne s'en plaigne.
     */
    private function relancesAutomatiques(): array
    {
        $depuis = now()->subDays(13)->startOfDay();

        $parJour = NotificationUtilisateur::where('type', 'essai_rappel')
            ->where('created_at', '>=', $depuis)
            ->selectRaw('DATE(created_at) as jour, COUNT(*) as nombre')
            ->groupBy('jour')
            ->pluck('nombre', 'jour');

        $historique = collect(range(13, 0))
            ->map(fn (int $i) => now()->subDays($i)->toDateString())
            ->map(fn (string $date) => [
                'date' => $date,
                'nombre' => (int) ($parJour->get($date) ?? 0),
            ])
            ->values()
            ->all();

        $derniere = NotificationUtilisateur::where('type', 'essai_rappel')->latest('created_at')->first();

        return [
            'historique' => $historique,
            'aujourdhui' => (int) ($parJour->get(now()->toDateString()) ?? 0),
            'derniere_execution_a' => $derniere?->created_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Données réelles calculées depuis essais_utilisateurs — jamais de valeurs
     * fictives, conforme au cahier des charges. Un seul essai par utilisateur compté
     * (le plus récent, voir essaisUtilisateurs()) -- sinon un utilisateur réactivé
     * serait compté deux fois (une fois dans "expirés", une fois dans "actifs").
     */
    private function statistiques(): array
    {
        $dernierEssaiParUtilisateur = EssaiUtilisateur::selectRaw('MAX(id) as id')->groupBy('user_id');
        $essais = EssaiUtilisateur::whereIn('id', $dernierEssaiParUtilisateur)->get(['id', 'date_fin', 'converti_a', 'annule_a']);

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
