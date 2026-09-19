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
        $this->creerPlanBasique();
        $this->creerPlanGratuit();

        $converti = $this->inscrire('converti@example.com');
        EssaiUtilisateur::where('user_id', $converti->id)->update(['converti_a' => now()]);

        $expire = $this->inscrire('expire@example.com');
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
}
