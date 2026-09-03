<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Plan;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class FactureModeleTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    private function payloadFacture(int $clientId, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $clientId,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [
                ['designation' => 'Ligne libre', 'quantite' => 1, 'prix_unitaire' => 1000, 'tva_taux' => 0],
            ],
        ], $overrides);
    }

    // TEST 1 — Gratuit : seuls les modèles 1 et 2 sont utilisables à la création.
    public function test_free_plan_user_can_use_only_free_templates_when_creating(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 1]))->assertRedirect();
        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 2]))->assertRedirect();

        $this->assertSame(2, Facture::count());
    }

    // TEST 2 — Gratuit : tentative avec un modèle payant (10) via requête directe -> 403, rien créé.
    public function test_free_plan_user_creating_invoice_with_paid_template_is_forbidden(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $response = $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 10]));

        $response->assertStatus(403);
        $this->assertSame(0, Facture::count());
    }

    // TEST 3 — Basique/Pro : les 10 modèles sont utilisables.
    public function test_paid_plan_user_can_create_invoice_with_any_template(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['modeles_facture_avances' => true]);
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        foreach ([1, 5, 10] as $modeleId) {
            $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => $modeleId]))->assertRedirect();
        }

        $this->assertSame(3, Facture::count());
        $this->assertSame(10, Facture::latest('id')->first()->modele_id->value);
    }

    // TEST 4 — modele_id invalide (hors 1-10) -> 422 (validation), jamais 403 (autorisation).
    public function test_invalid_modele_id_returns_422_not_403(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        // postJson simule le comportement réel d'Inertia (Accept: application/json), qui
        // reçoit un vrai 422 sur échec de validation plutôt qu'une redirection classique.
        $response = $this->postJson('/factures', $this->payloadFacture($client->id, ['modele_id' => 999]));

        $response->assertStatus(422);
        $response->assertInvalid('modele_id');
        $this->assertSame(0, Facture::count());
    }

    // TEST 5 — upgrade réel (flux paiement existant) : anciennes factures gardent leur
    // modele_id, les nouvelles factures se débloquent sur 3-10 sans recréation de compte.
    public function test_upgrade_grandfathers_old_invoices_template_and_unlocks_new_templates(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 2]))->assertRedirect();
        $ancienneFacture = Facture::latest('id')->first();
        $this->assertSame(2, $ancienneFacture->modele_id->value);

        // Tentative sur modèle payant avant upgrade -> toujours refusée.
        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 5]))->assertStatus(403);

        // Flux réel d'upgrade : demande -> paiement en_attente -> approbation admin.
        $planPayant = Plan::firstOrCreate(['code' => 'basique'], [
            'nom' => 'Basique', 'prix' => 5000, 'modeles_facture_avances' => true,
        ]);
        if (! $planPayant->modeles_facture_avances) {
            $planPayant->update(['modeles_facture_avances' => true]);
        }
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->post(route('abonnement.changer', $planPayant))->assertRedirect();
        $paiement = Paiement::where('user_id', $user->id)->where('statut', 'en_attente')->firstOrFail();
        $this->actingAs($admin)->post("/admin/paiements/{$paiement->id}/approuver")->assertRedirect();

        $this->actingAs($user);
        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 5]))->assertRedirect();

        $this->assertSame(2, $ancienneFacture->fresh()->modele_id->value, "L'ancienne facture ne doit jamais changer de modèle rétroactivement.");
        $this->assertSame(5, Facture::latest('id')->first()->modele_id->value);
    }

    // TEST 6 — downgrade/expiration : l'ancienne facture (modèle payant) reste consultable
    // et modifiable SANS changer de modèle ; la changer vers un autre modèle payant ou en
    // créer une nouvelle avec un modèle payant est refusé tant que le plan reste Gratuit.
    public function test_downgrade_preserves_existing_invoices_and_blocks_only_new_paid_template_choices(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['modeles_facture_avances' => true]);
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 8, 'statut' => 'brouillon']))->assertRedirect();
        $facture = Facture::latest('id')->first();
        $this->assertSame(8, $facture->modele_id->value);

        // L'abonnement expire -> plan actif redevient Gratuit (comportement déjà en place).
        $user->abonnements()->update(['date_fin' => now()->subDay()]);
        $this->assertNull($user->fresh()->planActif());

        // Toujours consultable et téléchargeable telle quelle.
        $this->get(route('factures.show', $facture))->assertOk();
        $this->get(route('factures.pdf', $facture))->assertOk();

        // Modifier sans changer de modèle -> autorisé.
        $this->put(route('factures.update', $facture), $this->payloadFacture($client->id, [
            'modele_id' => 8, 'notes' => 'Mise à jour sans changement de modèle',
        ]))->assertRedirect();
        $this->assertSame('Mise à jour sans changement de modèle', $facture->fresh()->notes);

        // Changer vers un autre modèle payant -> refusé.
        $this->put(route('factures.update', $facture), $this->payloadFacture($client->id, ['modele_id' => 3]))
            ->assertStatus(403);
        $this->assertSame(8, $facture->fresh()->modele_id->value);

        // Créer une nouvelle facture avec un modèle payant -> refusé.
        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 3]))->assertStatus(403);
    }

    // TEST 7 — isolation boutique déjà en place, toujours vraie avec modele_id présent.
    public function test_boutique_scoped_product_and_client_isolation_still_enforced_with_modele_id(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $autreUtilisateur = $this->creerUtilisateurAvecBoutique('pro');

        $clientAutreBoutique = Client::create(['boutique_id' => $autreUtilisateur->current_boutique_id, 'nom' => 'Client étranger', 'etiquette' => 'client']);
        $produitAutreBoutique = Produit::create([
            'boutique_id' => $autreUtilisateur->current_boutique_id, 'type' => 'produit',
            'nom' => 'Produit étranger', 'prix_vente' => 500, 'unite' => 'pièce',
        ]);

        $this->actingAs($user);

        $response = $this->post('/factures', [
            'client_id' => $clientAutreBoutique->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'modele_id' => 1,
            'lignes' => [['produit_id' => $produitAutreBoutique->id, 'designation' => 'X', 'quantite' => 1, 'prix_unitaire' => 500, 'tva_taux' => 0]],
        ]);

        $response->assertInvalid(['client_id', 'lignes.0.produit_id']);
        $this->assertSame(0, Facture::count());
    }

    // TEST 8 — les montants forgés dans la requête sont toujours recalculés côté serveur.
    public function test_server_recalculates_totals_even_if_forged_montant_ttc_is_submitted(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $this->post('/factures', [
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'modele_id' => 1,
            'lignes' => [[
                'designation' => 'Ligne', 'quantite' => 2, 'prix_unitaire' => 1000, 'tva_taux' => 0,
                // Champs forgés : le client ne peut jamais dicter les montants.
                'montant_ht' => 1, 'montant_tva' => 1, 'montant_ttc' => 1,
            ]],
        ])->assertRedirect();

        $facture = Facture::latest('id')->first();
        $this->assertEquals(2000, $facture->sous_total);
        $this->assertEquals(2000, $facture->total_ttc);
        $this->assertEquals(2000, $facture->lignes->first()->montant_ttc);
    }

    // TEST 9 — duplication : clone lignes/client, retombe sur le modèle 1 si l'original
    // n'est plus autorisé pour le plan actuel.
    public function test_duplicate_invoice_falls_back_to_free_template_when_original_no_longer_authorized(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['modeles_facture_avances' => true]);
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => 9]))->assertRedirect();
        $original = Facture::latest('id')->first();

        // Le plan payant expire et l'utilisateur revient sur un vrai plan Gratuit (comme le
        // ferait la commande abonnements:expirer) — pas un état sans abonnement du tout,
        // qui ferait échouer le contrôle de quota (LimiteService) pour une tout autre raison.
        $user->abonnements()->update(['statut' => 'expire', 'date_fin' => now()->subDay()]);
        $planGratuit = Plan::firstOrCreate(['code' => 'gratuit'], [
            'nom' => 'Gratuit', 'prix' => 0, 'modeles_facture_avances' => false,
        ]);
        $user->abonnements()->create(['plan_id' => $planGratuit->id, 'statut' => 'actif', 'date_debut' => now()]);
        $this->assertSame('gratuit', $user->fresh()->planActif()->code);

        $response = $this->post(route('factures.dupliquer', $original));
        $response->assertRedirect();

        $copie = Facture::latest('id')->first();
        $this->assertNotSame($original->id, $copie->id);
        $this->assertSame(1, $copie->modele_id->value, 'Doit retomber sur le modèle Standard, jamais bloquer la duplication.');
        $this->assertSame('brouillon', $copie->statut);
        $this->assertSame($client->id, $copie->client_id);
        $this->assertSame(1, $copie->lignes()->count());
    }

    // Le champ "garantie" est propre à chaque facture (pas à la boutique comme
    // note_pied_facture) : deux factures de la même boutique peuvent avoir des garanties
    // différentes, et il apparaît correctement dans l'aperçu construit côté serveur.
    public function test_garantie_is_saved_per_invoice_and_appears_in_the_preview(): void
    {
        $user = $this->creerUtilisateurAvecBoutique('gratuit');
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user);

        $this->post('/factures', $this->payloadFacture($client->id, [
            'garantie' => "Garantie de 6 mois. Ne couvre pas l'eau, les chocs ou une mauvaise utilisation.",
        ]))->assertRedirect();

        $facture = Facture::latest('id')->first();
        $this->assertSame("Garantie de 6 mois. Ne couvre pas l'eau, les chocs ou une mauvaise utilisation.", $facture->garantie);

        $this->get(route('factures.show', $facture))
            ->assertInertia(fn ($page) => $page->where('apercu.garantie', $facture->garantie));

        // Une seconde facture de la même boutique, sans garantie précisée, ne doit rien
        // hériter de la première (le champ est bien par facture, pas par boutique).
        $this->post('/factures', $this->payloadFacture($client->id))->assertRedirect();
        $secondeFacture = Facture::latest('id')->first();
        $this->assertNull($secondeFacture->garantie);
    }
}
