<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\FactureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesBoutique;
use Tests\TestCase;

class BoutiqueNuiTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    // Le NUI est modifiable en un clic depuis l'écran de saisie de facture
    // (FactureForm.vue -> boutiques.nui), sans avoir à quitter la création de facture.
    public function test_owner_can_update_nui_from_the_quick_save_route(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($user)->patch("/boutiques/{$user->current_boutique_id}/nui", [
            'nui' => 'M012312345678A',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('boutiques', ['id' => $user->current_boutique_id, 'nui' => 'M012312345678A']);
    }

    public function test_non_owner_cannot_update_another_users_boutique_nui(): void
    {
        $proprietaire = $this->creerUtilisateurAvecBoutique();
        $intrus = $this->creerUtilisateurAvecBoutique();

        $response = $this->actingAs($intrus)->patch("/boutiques/{$proprietaire->current_boutique_id}/nui", [
            'nui' => 'FORCÉ',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('boutiques', ['id' => $proprietaire->current_boutique_id, 'nui' => 'FORCÉ']);
    }

    // Une fois enregistré, le NUI apparaît dans l'aperçu de facture (le pipeline complet
    // FactureApercuBuilder -> modèle de facture reflète bien la valeur mise à jour).
    public function test_nui_appears_in_invoice_preview_after_being_set(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $this->actingAs($user)->patch("/boutiques/{$user->current_boutique_id}/nui", ['nui' => 'M012312345678A']);

        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $facture = app(FactureService::class)->creer([
            'client_id' => $client->id,
            'date_emission' => now()->toDateString(),
            'remise' => 0,
            'lignes' => [['designation' => 'Ligne', 'quantite' => 1, 'prix_unitaire' => 1000, 'tva_taux' => 0]],
        ], $user, $user->current_boutique_id);

        $this->actingAs($user)->get("/factures/{$facture->id}")
            ->assertInertia(fn ($page) => $page->where('apercu.boutique.nui', 'M012312345678A'));
    }
}
