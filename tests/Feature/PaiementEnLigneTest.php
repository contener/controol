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

    public function test_statut_sans_montant_confirme_par_le_montant_de_la_notification_signee(): void
    {
        $this->camerpay('completed', null);
        [$user, $reference] = $this->demarrer();

        // Suivi par l'application seul : montant inconnu, jamais approuvé.
        $this->actingAs($user, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")->assertJsonPath('statut', 'en_cours');

        // Notification signée avec le bon montant : approuvé.
        $signature = hash_hmac('sha256', 'uuid-123|'.$reference.'|completed|5000.00', self::SECRET);
        $this->post('/api/webhooks/camerpay', ['uuid' => 'uuid-123', 'invoice_id' => $reference, 'status' => 'completed', 'amount' => '5000.00', 'signature' => $signature])->assertOk();
        $this->assertSame(Paiement::STATUT_APPROUVE, Paiement::first()->statut);
    }

    public function test_notification_signee_avec_mauvais_montant_jamais_approuvee(): void
    {
        $this->camerpay('completed', null);
        [, $reference] = $this->demarrer();

        $signature = hash_hmac('sha256', 'uuid-123|'.$reference.'|completed|100', self::SECRET);
        $this->post('/api/webhooks/camerpay', ['uuid' => 'uuid-123', 'invoice_id' => $reference, 'status' => 'completed', 'amount' => '100', 'signature' => $signature])->assertOk();
        $this->assertSame('en_attente', Paiement::first()->statut);
    }

    public function test_sous_domaine_camerpay_accepte_et_telephone_normalise(): void
    {
        $this->camerpay(payUrl: 'https://pay.camerpay.biz/checkout/abc');
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $user->forceFill(['telephone' => '6 90 12 34 56'])->save();
        $plan = $this->plan();

        $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$plan->id}/payer")->assertCreated();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/initiate') && $r['customer_phone'] === '+237690123456');
    }

    public function test_page_de_paiement_de_test_refusee(): void
    {
        // Paiements réels uniquement : une page de paiement d'un serveur de test est refusée.
        $this->camerpay(payUrl: 'https://tespay.ntas-services.com/pay/abc');
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $plan = $this->plan();

        $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$plan->id}/payer")->assertStatus(503);
    }

    public function test_telephone_invalide_non_envoye(): void
    {
        $this->camerpay();
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $user->forceFill(['telephone' => '12345'])->save();
        $plan = $this->plan();

        $this->actingAs($user, 'sanctum')->postJson("/api/abonnement/plans/{$plan->id}/payer")->assertCreated();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment/initiate') && ! isset($r['customer_phone']));
    }

    public function test_historique_montre_les_statuts_sans_information_sensible(): void
    {
        $this->camerpay('completed');
        [$user, $reference] = $this->demarrer();
        $this->actingAs($user, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")->assertJsonPath('statut', 'confirmee');

        // Un paiement d'un autre utilisateur ne doit jamais apparaître.
        $autre = User::factory()->create();
        Paiement::create(['user_id' => $autre->id, 'montant' => 999, 'devise' => 'XAF', 'statut' => 'rejete', 'motif_rejet' => 'secret']);

        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/abonnement/paiements')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.statut', 'paye')
            ->assertJsonPath('data.0.statut_libelle', 'Payé')
            ->assertJsonPath('data.0.montant', 5000)
            ->assertJsonPath('data.0.plan', 'Basique')
            ->assertJsonPath('data.0.tentatives.0.statut', 'confirmee');

        $brut = $reponse->getContent();
        foreach (['uuid-123', $reference, 'valide_par', 'admin', 'ip_address', 'empreinte', 'user_id', 'reference_prestataire', 'secret'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $brut, "« {$interdit} » ne doit pas être exposé");
        }
        // Références masquées : seuls les 6 derniers caractères.
        $this->assertSame('…'.substr('uuid-123', -6), $reponse->json('data.0.reference'));
    }

    public function test_historique_statuts_echoue_et_en_attente(): void
    {
        $this->camerpay('failed');
        [$user, $reference] = $this->demarrer();
        $this->actingAs($user, 'sanctum')->getJson("/api/abonnement/paiements/{$reference}")->assertJsonPath('statut', 'echouee');

        $this->actingAs($user, 'sanctum')->getJson('/api/abonnement/paiements')
            ->assertJsonPath('data.0.statut', 'echoue')
            ->assertJsonPath('data.0.statut_libelle', 'Échoué');

        // Ancien parcours (sans tentative en ligne) : en attente de validation.
        $user2 = $this->creerUtilisateurAvecBoutique('gratuit');
        Paiement::create(['user_id' => $user2->id, 'montant' => 5000, 'devise' => 'XAF', 'statut' => 'en_attente']);
        $this->actingAs($user2, 'sanctum')->getJson('/api/abonnement/paiements')->assertJsonPath('data.0.statut', 'en_attente');

        // Paiement en attente exposé en liste blanche seulement.
        $this->actingAs($user2, 'sanctum')->getJson('/api/abonnement')
            ->assertJsonPath('paiement_en_attente.montant', '5000.00')
            ->assertJsonMissingPath('paiement_en_attente.valide_par')
            ->assertJsonMissingPath('paiement_en_attente.user_id');
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
