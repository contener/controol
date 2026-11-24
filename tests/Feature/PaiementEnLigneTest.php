<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\PaiementAudit;
use App\Models\Plan;
use App\Models\TentativePaiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesBoutique;
use Tests\TestCase;

/**
 * Paiement en ligne des abonnements depuis l'application (CamerPay, 2026-11-24) :
 * montant et confirmation déterminés uniquement côté serveur -- voir
 * PaiementEnLigneService.
 */
class PaiementEnLigneTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private const SECRET = 'secret-de-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.camerpay.base_url' => 'https://camerpay.biz/api',
            'services.camerpay.api_key' => 'cle-de-test',
            'services.camerpay.webhook_secret' => self::SECRET,
        ]);
    }

    private function plan(): Plan
    {
        return Plan::create(['code' => 'basique', 'nom' => 'Basique', 'prix' => 5000, 'devise' => 'XAF', 'duree_jours' => 30]);
    }

    /** CamerPay simulé : initiation acceptée, puis statut renvoyé par $statut. */
    private function camerpay(string $statut = 'pending', ?float $montant = 5000, string $payUrl = 'https://camerpay.biz/pay/abc'): void
    {
        Http::fake([
            'camerpay.biz/api/payment/initiate' => Http::response(['success' => true, 'pay_url' => $payUrl, 'transaction' => ['uuid' => 'uuid-123', 'status' => 'pending']]),
            'camerpay.biz/api/payment/uuid-123/status' => Http::response(['transaction' => array_filter(['uuid' => 'uuid-123', 'status' => $statut, 'amount' => $montant, 'currency' => 'XAF'], fn ($v) => $v !== null)]),
        ]);
    }

    /** @return array{0: User, 1: string} */
    private function demarrer(): array
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $plan = $this->plan();

        $reponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/abonnement/plans/{$plan->id}/payer", ['montant' => 1])
            ->assertCreated()
            ->assertJsonPath('pay_url', 'https://camerpay.biz/pay/abc')
            ->assertJsonPath('montant', 5000);

        return [$user, $reponse->json('reference')];
    }

    public function test_paiement_demarre_avec_le_montant_du_serveur_et_la_cle_jamais_exposee(): void
    {
        $this->camerpay();
        [$user, $reference] = $this->demarrer();

        // Le montant envoyé par l'application (1) est ignoré : 5000, calculé par le serveur.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/initiate')
            && $r['amount'] === 5000
            && $r['merchant_invoice_id'] === $reference
            && $r->hasHeader('Authorization', 'Bearer cle-de-test'));

        $this->assertStringStartsWith('CTRL-', $reference);
        $this->assertSame('en_attente', Paiement::first()->statut);
        $this->assertSame('uuid-123', TentativePaiement::first()->reference_prestataire);

        $this->actingAs($user, 'sanctum')->getJson('/api/abonnement')->assertJsonPath('paiement_en_ligne', true)
            ->assertJsonMissing(['api_key' => 'cle-de-test']);
    }

    public function test_paiement_confirme_par_le_serveur_active_l_abonnement_une_seule_fois(): void
    {
        $this->camerpay('completed');
        [$user, $reference] = $this->demarrer();

        $this->actingAs($user, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")
            ->assertOk()->assertJsonPath('statut', 'confirmee');

        $paiement = Paiement::first();
        $this->assertSame(Paiement::STATUT_APPROUVE, $paiement->statut);
        $this->assertSame('camerpay', $paiement->moyen_paiement);
        $this->assertSame('uuid-123', $paiement->reference_transaction);
        $this->assertSame('basique', $user->fresh()->planActif()->code);
        $this->assertSame(1, PaiementAudit::where('action', 'paiement_en_ligne')->whereNull('admin_id')->count());

        // Rejouer la vérification n'active jamais une seconde fois.
        $this->travel(10)->seconds();
        $this->actingAs($user, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")->assertJsonPath('statut', 'confirmee');
        $this->assertSame(1, PaiementAudit::count());
    }

    private function verifierJamaisApprouve(?float $montantRenvoye): void
    {
        $this->camerpay('completed', $montantRenvoye);
        [$user, $reference] = $this->demarrer();

        $this->actingAs($user, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")->assertJsonPath('statut', 'en_cours');
        $this->assertSame('en_attente', Paiement::first()->statut);
    }

    public function test_montant_non_concordant_jamais_approuve(): void
    {
        $this->verifierJamaisApprouve(100.0);
    }

    public function test_montant_absent_de_la_reponse_jamais_approuve(): void
    {
        $this->verifierJamaisApprouve(null);
    }

    public function test_notification_signee_declenche_la_verification(): void
    {
        $this->camerpay('completed');
        [, $reference] = $this->demarrer();

        $donnees = ['uuid' => 'uuid-123', 'invoice_id' => $reference, 'status' => 'completed', 'amount' => '5000'];

        // Signature invalide : refusée, rien n'est approuvé.
        $this->post('/api/webhooks/camerpay', [...$donnees, 'signature' => 'faux'])->assertStatus(401);
        $this->assertSame('en_attente', Paiement::first()->statut);

        $signature = hash_hmac('sha256', 'uuid-123|'.$reference.'|completed|5000', self::SECRET);
        $this->post('/api/webhooks/camerpay', $donnees, ['X-CamerPay-Signature' => $signature])->assertOk()->assertSee('OK');

        $this->assertSame(Paiement::STATUT_APPROUVE, Paiement::first()->statut);
        $this->assertNotNull(TentativePaiement::first()->empreinte_notification);
    }

    public function test_notification_signee_mais_camerpay_dit_en_attente_n_approuve_rien(): void
    {
        $this->camerpay('pending');
        [, $reference] = $this->demarrer();

        $signature = hash_hmac('sha256', 'uuid-123|'.$reference.'|completed|5000', self::SECRET);
        $this->post('/api/webhooks/camerpay', ['uuid' => 'uuid-123', 'invoice_id' => $reference, 'status' => 'completed', 'amount' => '5000', 'signature' => $signature])->assertOk();

        $this->assertSame('en_attente', Paiement::first()->statut);
    }

    public function test_reference_d_un_autre_utilisateur_introuvable(): void
    {
        $this->camerpay('completed');
        [, $reference] = $this->demarrer();
        $intrus = $this->creerUtilisateurAvecBoutique('gratuit');

        $this->actingAs($intrus, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")->assertNotFound();
        $this->assertSame('en_attente', Paiement::first()->statut);
    }

    public function test_url_de_paiement_hors_camerpay_refusee(): void
    {
        $this->camerpay(payUrl: 'https://pirate.example/pay');
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $plan = $this->plan();

        $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$plan->id}/payer")->assertStatus(503);
        $this->assertSame(TentativePaiement::ECHOUEE, TentativePaiement::first()->statut);
    }

    public function test_paiement_en_ligne_indisponible_sans_configuration(): void
    {
        config(['services.camerpay.api_key' => null]);
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $plan = $this->plan();

        $this->actingAs($user, 'sanctum')->getJson('/api/abonnement')->assertJsonPath('paiement_en_ligne', false);
        $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$plan->id}/payer")->assertStatus(503);
    }
}
