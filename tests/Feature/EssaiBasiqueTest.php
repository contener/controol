<?php

namespace Tests\Feature;

use App\Enums\EssaiStatut;
use App\Models\AdminPermission;
use App\Models\EssaiUtilisateur;
use App\Models\ModeleNotificationEssai;
use App\Models\NotificationUtilisateur;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class EssaiBasiqueTest extends TestCase
{
    use RefreshDatabase;

    private function creerPlanBasique(): Plan
    {
        return Plan::create(['code' => 'basique', 'nom' => 'Basique', 'prix' => 5000, 'devise' => 'XAF', 'duree_jours' => 30]);
    }

    private function creerPlanGratuit(): Plan
    {
        return Plan::create(['code' => 'gratuit', 'nom' => 'Gratuit', 'prix' => 0, 'devise' => 'XAF']);
    }

    private function inscrire(string $email = 'test@example.com'): User
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
        ]);

        return User::where('email', $email)->firstOrFail();
    }

    /**
     * date_fin est un instantane pris a la creation sur DEUX tables (essais_utilisateurs
     * et abonnements) -- en usage reel, plus rien ne les modifie separement apres coup
     * (voir la docblock de EssaiUtilisateur::statut()). Pour simuler une expiration en
     * test, les deux doivent donc etre mis a jour ensemble, jamais un seul.
     */
    private function expirerEssai(EssaiUtilisateur $essai): void
    {
        $essai->update(['date_fin' => now()->subDay()]);
        $essai->abonnement->update(['date_fin' => now()->subDay()]);
    }

    private function creerAdminAvecPermissions(array $permissions): User
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ($permissions as $permission) {
            AdminPermission::create(['user_id' => $admin->id, 'permission' => $permission]);
        }

        return $admin;
    }

    /**
     * Construit directement un utilisateur + son essai (sans passer par /register) --
     * /register est une route "guest" : appeler inscrire() deux fois dans le même test
     * échouerait, le second appel étant redirigé puisque le premier utilisateur reste
     * connecté en session.
     */
    private function creerUtilisateurAvecEssai(string $email, Plan $planBasique): User
    {
        $user = User::factory()->create(['email' => $email]);
        $abonnement = \App\Models\Abonnement::create([
            'user_id' => $user->id, 'plan_id' => $planBasique->id, 'statut' => 'actif',
            'date_debut' => now(), 'date_fin' => now()->addDays(7),
        ]);
        EssaiUtilisateur::create([
            'user_id' => $user->id, 'abonnement_id' => $abonnement->id, 'plan_id' => $planBasique->id,
            'date_debut' => now(), 'date_fin' => now()->addDays(7), 'prix_promo' => 3500,
        ]);

        return $user;
    }

    public function test_registration_grants_a_7_day_basique_trial(): void
    {
        $this->creerPlanBasique();
        $this->creerPlanGratuit();

        $user = $this->inscrire();

        $this->assertSame('basique', $user->planActif()->code);

        $essai = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(EssaiStatut::EnCours, $essai->statut());
        $this->assertEqualsWithDelta(7, $essai->joursRestants(), 1);
        $this->assertSame('3500.00', (string) $essai->prix_promo);
    }

    public function test_registration_falls_back_to_gratuit_when_basique_plan_missing(): void
    {
        $this->creerPlanGratuit();

        $user = $this->inscrire();

        $this->assertSame('gratuit', $user->planActif()->code);
        $this->assertDatabaseCount('essais_utilisateurs', 0);
    }

    public function test_promo_price_applied_while_trial_is_active(): void
    {
        $basique = $this->creerPlanBasique();
        $this->creerPlanGratuit();
        $user = $this->inscrire();

        $this->actingAs($user)->post(route('abonnement.changer', $basique->id));

        $paiement = Paiement::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('3500.00', (string) $paiement->montant);
    }

    public function test_normal_price_applied_once_trial_has_expired(): void
    {
        $basique = $this->creerPlanBasique();
        $this->creerPlanGratuit();
        $user = $this->inscrire();

        $essai = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->expirerEssai($essai);

        $this->actingAs($user)->post(route('abonnement.changer', $basique->id));

        $paiement = Paiement::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('5000.00', (string) $paiement->montant);
    }

    public function test_approving_payment_marks_trial_as_converted(): void
    {
        $basique = $this->creerPlanBasique();
        $this->creerPlanGratuit();
        $user = $this->inscrire();

        $this->actingAs($user)->post(route('abonnement.changer', $basique->id));
        $paiement = Paiement::where('user_id', $user->id)->latest('id')->first();

        $admin = $this->creerAdminAvecPermissions(['paiements.valider']);
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/approuver")->assertRedirect();

        $essai = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($essai->fresh()->converti_a);
        $this->assertSame(EssaiStatut::Converti, $essai->fresh()->statut());
        $this->assertSame('basique', $user->fresh()->planActif()->code);
    }

    public function test_expired_trial_reverts_user_to_gratuit_via_existing_command(): void
    {
        $this->creerPlanBasique();
        $this->creerPlanGratuit();
        $user = $this->inscrire();

        $essai = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->expirerEssai($essai);

        Artisan::call('abonnements:expirer');

        $this->assertSame('gratuit', $user->fresh()->planActif()->code);
    }

    public function test_essais_notifier_creates_one_notification_and_is_idempotent(): void
    {
        $this->creerPlanBasique();
        $user = $this->inscrire();

        Artisan::call('essais:notifier');
        Artisan::call('essais:notifier');

        $this->assertDatabaseCount('notifications_utilisateurs', 1);

        $notification = NotificationUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->assertStringContainsString($user->name, $notification->message);
        $this->assertTrue($notification->est_promotionnelle);

        $modeleJour1 = ModeleNotificationEssai::where('jour', 1)->firstOrFail();
        $this->assertSame($modeleJour1->titre, $notification->titre);
    }

    public function test_essais_notifier_skips_converted_and_expired_trials(): void
    {
        $planBasique = $this->creerPlanBasique();
        $this->creerPlanGratuit();

        $converti = $this->creerUtilisateurAvecEssai('converti@example.com', $planBasique);
        EssaiUtilisateur::where('user_id', $converti->id)->update(['converti_a' => now()]);

        $expire = $this->creerUtilisateurAvecEssai('expire@example.com', $planBasique);
        $essaiExpire = EssaiUtilisateur::where('user_id', $expire->id)->firstOrFail();
        $this->expirerEssai($essaiExpire);

        Artisan::call('essais:notifier');

        $this->assertDatabaseCount('notifications_utilisateurs', 0);
    }

    public function test_admin_without_permission_cannot_view_notifications_admin_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/notifications')->assertStatus(403);
    }

    public function test_admin_with_permission_can_view_notifications_admin_page(): void
    {
        $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);

        $this->actingAs($admin)->get('/admin/notifications')->assertOk();
    }

    public function test_admin_without_envoyer_permission_cannot_edit_a_template(): void
    {
        $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);
        $modele = ModeleNotificationEssai::where('jour', 1)->firstOrFail();

        $this->actingAs($admin)->patch("/admin/notifications/modeles/{$modele->id}", [
            'titre' => 'Nouveau titre',
            'message' => 'Nouveau message',
            'actif' => true,
        ])->assertStatus(403);

        $this->assertNotSame('Nouveau titre', $modele->fresh()->titre);
    }

    public function test_admin_with_envoyer_permission_can_edit_a_template(): void
    {
        $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'notifications.envoyer']);
        $modele = ModeleNotificationEssai::where('jour', 1)->firstOrFail();

        $this->actingAs($admin)->patch("/admin/notifications/modeles/{$modele->id}", [
            'titre' => 'Nouveau titre',
            'message' => 'Nouveau message',
            'actif' => true,
        ])->assertRedirect();

        $this->assertSame('Nouveau titre', $modele->fresh()->titre);
    }

    public function test_user_can_mark_a_notification_and_all_notifications_as_read(): void
    {
        $this->creerPlanBasique();
        $user = $this->inscrire();

        $notification = NotificationUtilisateur::create([
            'user_id' => $user->id, 'type' => 'essai_rappel', 'titre' => 'T', 'message' => 'M', 'created_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/mes-notifications')->assertOk()->assertJsonCount(1, 'notifications');

        $this->actingAs($user)->patchJson("/mes-notifications/{$notification->id}/lu")->assertOk();
        $this->assertNotNull($notification->fresh()->lu_a);

        $notification2 = NotificationUtilisateur::create([
            'user_id' => $user->id, 'type' => 'essai_rappel', 'titre' => 'T2', 'message' => 'M2', 'created_at' => now(),
        ]);
        $this->actingAs($user)->patchJson('/mes-notifications/tout-lu')->assertOk();
        $this->assertNotNull($notification2->fresh()->lu_a);
    }

    public function test_admin_page_exposes_days_remaining_and_boutique_status_per_trial_user(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);

        $this->creerUtilisateurAvecEssai('sans-boutique@example.com', $planBasique);
        $avecBoutique = $this->creerUtilisateurAvecEssai('avec-boutique@example.com', $planBasique);
        \App\Models\Boutique::create([
            'user_id' => $avecBoutique->id, 'nom' => 'Boutique test', 'slug' => 'boutique-test-'.uniqid(),
            'devise' => 'XAF', 'taux_tva_defaut' => 19.25,
        ]);

        $response = $this->actingAs($admin)->get('/admin/notifications');

        $response->assertInertia(fn ($page) => $page
            ->where('essais.data', fn ($essais) => collect($essais)->contains(fn ($e) => $e['email'] === 'sans-boutique@example.com' && $e['a_boutique'] === false && $e['statut'] === 'en_cours' && $e['jours_restants'] === 7)
                && collect($essais)->contains(fn ($e) => $e['email'] === 'avec-boutique@example.com' && $e['a_boutique'] === true)));
    }

    public function test_admin_page_filters_trial_users_by_statut(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);

        $this->creerUtilisateurAvecEssai('en-cours@example.com', $planBasique);
        $expireUtilisateur = $this->creerUtilisateurAvecEssai('expire@example.com', $planBasique);
        $this->expirerEssai(EssaiUtilisateur::where('user_id', $expireUtilisateur->id)->firstOrFail());

        $this->actingAs($admin)->get('/admin/notifications?statutEssai=en_cours')
            ->assertInertia(fn ($page) => $page
                ->where('essais.data', fn ($essais) => collect($essais)->pluck('email')->contains('en-cours@example.com')
                    && ! collect($essais)->pluck('email')->contains('expire@example.com')));

        $this->actingAs($admin)->get('/admin/notifications?statutEssai=expire')
            ->assertInertia(fn ($page) => $page
                ->where('essais.data', fn ($essais) => collect($essais)->pluck('email')->contains('expire@example.com')
                    && ! collect($essais)->pluck('email')->contains('en-cours@example.com')));
    }

    public function test_whatsapp_number_hidden_from_trial_list_without_voir_permission(): void
    {
        $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);
        $user = $this->inscrire();
        $user->update(['whatsapp' => '+237600000000']);

        $this->actingAs($admin)->get('/admin/notifications')
            ->assertInertia(fn ($page) => $page
                ->where('essais.data', fn ($essais) => collect($essais)->first()['whatsapp'] === null)
                ->where('permissionsWhatsapp.voir', false));
    }

    public function test_whatsapp_relance_templates_for_trial_users_exist_and_are_context_aware(): void
    {
        $cles = \App\Support\WhatsappModeles::cles();

        $this->assertContains('essai_relance_sans_boutique', $cles);
        $this->assertContains('essai_relance_avec_boutique', $cles);

        $modeles = collect(\App\Support\WhatsappModeles::liste())->keyBy('cle');
        $this->assertStringContainsString('{{jours_restants}}', $modeles['essai_relance_sans_boutique']['texte']);
        $this->assertStringContainsString('boutique', $modeles['essai_relance_sans_boutique']['texte']);
        $this->assertStringContainsString('{{jours_restants}}', $modeles['essai_relance_avec_boutique']['texte']);
        $this->assertStringContainsString('abonnement', $modeles['essai_relance_avec_boutique']['texte']);
    }

    public function test_admin_can_relaunch_a_trial_user_via_whatsapp_from_the_trial_list(): void
    {
        $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'whatsapp.contacter']);
        $user = $this->inscrire();
        $user->update(['whatsapp' => '+237600000000']);

        $response = $this->actingAs($admin)->postJson("/admin/utilisateurs/{$user->id}/whatsapp", [
            'message' => 'Message de test',
            'modele_cle' => 'essai_relance_sans_boutique',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('whatsapp_contact_logs', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'modele_cle' => 'essai_relance_sans_boutique',
        ]);
    }

    public function test_trial_list_shows_relaunch_confirmation_status(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'whatsapp.contacter', 'whatsapp.historique']);
        $user = $this->creerUtilisateurAvecEssai('relance@example.com', $planBasique);

        // Avant toute relance : "jamais relancé".
        $this->actingAs($admin)->get('/admin/notifications')
            ->assertInertia(fn ($page) => $page
                ->where('essais.data', fn ($essais) => collect($essais)->firstWhere('email', 'relance@example.com')['derniere_relance'] === null));

        $log = \App\Models\WhatsappContactLog::create([
            'user_id' => $user->id, 'admin_id' => $admin->id, 'numero_whatsapp' => '+237600000000',
            'message' => 'Bonjour', 'modele_cle' => 'essai_relance_sans_boutique', 'ouvert_a' => now(),
        ]);

        // Relancé mais pas encore confirmé.
        $this->actingAs($admin)->get('/admin/notifications')
            ->assertInertia(fn ($page) => $page
                ->where('essais.data', function ($essais) {
                    $relance = collect($essais)->firstWhere('email', 'relance@example.com')['derniere_relance'];

                    return $relance !== null && $relance['confirme'] === false;
                }));

        // Confirmation de l'envoi, réutilise l'endpoint existant.
        $this->actingAs($admin)->patch("/admin/utilisateurs/whatsapp-logs/{$log->id}/confirmer")->assertRedirect();
        $this->assertNotNull($log->fresh()->confirme_a);

        $this->actingAs($admin)->get('/admin/notifications')
            ->assertInertia(fn ($page) => $page
                ->where('essais.data', function ($essais) {
                    $relance = collect($essais)->firstWhere('email', 'relance@example.com')['derniere_relance'];

                    return $relance !== null && $relance['confirme'] === true;
                }));
    }

    public function test_relaunch_confirmation_status_hidden_without_historique_permission(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);
        $user = $this->creerUtilisateurAvecEssai('sans-historique@example.com', $planBasique);

        \App\Models\WhatsappContactLog::create([
            'user_id' => $user->id, 'admin_id' => $admin->id, 'numero_whatsapp' => '+237600000000',
            'message' => 'Bonjour', 'modele_cle' => 'essai_relance_sans_boutique', 'ouvert_a' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/notifications')
            ->assertInertia(fn ($page) => $page
                ->where('permissionsWhatsapp.historique', false)
                ->where('essais.data', fn ($essais) => collect($essais)->firstWhere('email', 'sans-historique@example.com')['derniere_relance'] === null));
    }

    public function test_admin_with_envoyer_permission_can_reactivate_an_expired_trial(): void
    {
        $planBasique = $this->creerPlanBasique();
        $this->creerPlanGratuit();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'notifications.envoyer']);
        $user = $this->creerUtilisateurAvecEssai('expire-reactive@example.com', $planBasique);
        $essaiExpire = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->expirerEssai($essaiExpire);
        Artisan::call('abonnements:expirer');
        $this->assertSame('gratuit', $user->fresh()->planActif()->code);

        $response = $this->actingAs($admin)->post("/admin/notifications/essais/{$essaiExpire->id}/reactiver");

        $response->assertRedirect();
        $this->assertSame(2, EssaiUtilisateur::where('user_id', $user->id)->count());
        $nouvelEssai = EssaiUtilisateur::where('user_id', $user->id)->where('id', '!=', $essaiExpire->id)->firstOrFail();
        $this->assertSame(EssaiStatut::EnCours, $nouvelEssai->statut());
        $this->assertSame(7, $nouvelEssai->joursRestants());
        $this->assertSame('basique', $user->fresh()->planActif()->code);

        // L'ancien essai expiré reste inchangé (historique jamais réécrit).
        $this->assertSame(EssaiStatut::Expire, $essaiExpire->fresh()->statut());

        $this->assertDatabaseHas('admin_audits', ['admin_id' => $admin->id, 'action' => 'essai_reactive', 'resource_id' => $essaiExpire->id]);
        $this->assertDatabaseHas('notifications_utilisateurs', ['user_id' => $user->id, 'type' => 'essai_rappel']);
    }

    /**
     * Après réactivation, l'utilisateur a DEUX lignes essais_utilisateurs (l'historique
     * n'est jamais réécrit) -- mais la liste admin ne doit en montrer qu'une seule, la
     * plus récente, sinon l'admin voit encore "Expiré" sur l'ancienne ligne et croit que
     * la réactivation n'a rien fait (bug réellement rencontré en production).
     */
    public function test_trial_list_shows_only_the_most_recent_trial_per_user_after_reactivation(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'notifications.envoyer']);
        $user = $this->creerUtilisateurAvecEssai('dedup@example.com', $planBasique);
        $essaiExpire = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->expirerEssai($essaiExpire);

        $this->actingAs($admin)->post("/admin/notifications/essais/{$essaiExpire->id}/reactiver")->assertRedirect();
        $this->assertSame(2, EssaiUtilisateur::where('user_id', $user->id)->count());

        $response = $this->actingAs($admin)->get('/admin/notifications');

        $response->assertInertia(fn ($page) => $page
            ->where('essais.data', function ($essais) {
                $lignesUtilisateur = collect($essais)->where('email', 'dedup@example.com');

                return $lignesUtilisateur->count() === 1 && $lignesUtilisateur->first()['statut'] === 'en_cours';
            }));
    }

    public function test_reactivation_success_flash_message_is_returned(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'notifications.envoyer']);
        $user = $this->creerUtilisateurAvecEssai('flash-reactive@example.com', $planBasique);
        $essaiExpire = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->expirerEssai($essaiExpire);

        $this->actingAs($admin)
            ->from('/admin/notifications')
            ->post("/admin/notifications/essais/{$essaiExpire->id}/reactiver")
            ->assertRedirect('/admin/notifications')
            ->assertSessionHas('flash_success');
    }

    public function test_admin_without_envoyer_permission_cannot_reactivate_a_trial(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir']);
        $user = $this->creerUtilisateurAvecEssai('expire-refuse@example.com', $planBasique);
        $essaiExpire = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();
        $this->expirerEssai($essaiExpire);

        $this->actingAs($admin)->post("/admin/notifications/essais/{$essaiExpire->id}/reactiver")->assertStatus(403);
        $this->assertSame(1, EssaiUtilisateur::where('user_id', $user->id)->count());
    }

    public function test_cannot_reactivate_a_trial_that_is_not_expired(): void
    {
        $planBasique = $this->creerPlanBasique();
        $admin = $this->creerAdminAvecPermissions(['notifications.voir', 'notifications.envoyer']);
        $user = $this->creerUtilisateurAvecEssai('en-cours-refuse@example.com', $planBasique);
        $essaiEnCours = EssaiUtilisateur::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($admin)->post("/admin/notifications/essais/{$essaiEnCours->id}/reactiver")->assertStatus(422);
        $this->assertSame(1, EssaiUtilisateur::where('user_id', $user->id)->count());
    }
}
