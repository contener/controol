<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Facture;
use App\Support\FactureApercuBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesBoutique;
use Tests\TestCase;

class FactureLogoWatermarkTest extends TestCase
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

    // Le logo doit être embarqué en data URI (dompdf refuse les images distantes par
    // défaut : une simple URL http(s) ne s'affiche jamais dans le PDF généré), jamais une
    // URL http(s) qui ne fonctionnerait pas côté dompdf.
    public function test_logo_is_embedded_as_a_base64_data_uri_not_a_remote_url(): void
    {
        Storage::fake('public');
        $user = $this->creerUtilisateurAvecBoutique();

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $chemin = $logo->store('boutiques/logos', 'public');
        $user->currentBoutique->update(['logo_path' => $chemin]);

        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client A', 'etiquette' => 'client']);
        $this->actingAs($user)->post('/factures', $this->payloadFacture($client->id));
        $facture = Facture::latest('id')->first();

        $apercu = app(FactureApercuBuilder::class)->construire($facture->fresh());

        $this->assertNotNull($apercu['boutique']['logo_url']);
        $this->assertStringStartsWith('data:image/png;base64,', $apercu['boutique']['logo_url']);
        $this->assertStringNotContainsString('http', $apercu['boutique']['logo_url']);
    }

    // Sans logo, aucun crash : logo_url doit rester null (aucun bloc logo/filigrane ne
    // s'affiche alors dans les templates PDF, cf. les conditions @if dans chaque modèle).
    public function test_logo_url_is_null_without_a_shop_logo(): void
    {
        $user = $this->creerUtilisateurAvecBoutique();
        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client B', 'etiquette' => 'client']);
        $this->actingAs($user)->post('/factures', $this->payloadFacture($client->id));
        $facture = Facture::latest('id')->first();

        $apercu = app(FactureApercuBuilder::class)->construire($facture->fresh());

        $this->assertNull($apercu['boutique']['logo_url']);
    }

    // Un logo_path qui pointe vers un fichier absent (supprimé manuellement, disque
    // incohérent...) ne doit jamais faire planter la génération du PDF.
    public function test_missing_logo_file_does_not_break_pdf_generation(): void
    {
        Storage::fake('public');
        $user = $this->creerUtilisateurAvecBoutique();
        $user->currentBoutique->update(['logo_path' => 'boutiques/logos/fichier-inexistant.png']);

        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client C', 'etiquette' => 'client']);
        $this->actingAs($user)->post('/factures', $this->payloadFacture($client->id));
        $facture = Facture::latest('id')->first();

        $apercu = app(FactureApercuBuilder::class)->construire($facture->fresh());
        $this->assertNull($apercu['boutique']['logo_url']);

        $this->get(route('factures.pdf', $facture))->assertOk();
    }

    // Chacun des 10 modèles doit inclure le partiel de filigrane, en plus de leur bloc
    // logo déjà existant en en-tête — les deux affichages (en-tête + filigrane) sont
    // attendus, pas un remplacement de l'un par l'autre.
    public function test_all_ten_pdf_templates_render_with_a_logo_and_watermark(): void
    {
        Storage::fake('public');
        $user = $this->creerUtilisateurAvecBoutique('pro');
        $user->planActif()->update(['modeles_facture_avances' => true]);

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $chemin = $logo->store('boutiques/logos', 'public');
        $user->currentBoutique->update(['logo_path' => $chemin]);

        $client = Client::create(['boutique_id' => $user->current_boutique_id, 'nom' => 'Client D', 'etiquette' => 'client']);
        $this->actingAs($user);

        foreach (range(1, 10) as $modeleId) {
            $this->post('/factures', $this->payloadFacture($client->id, ['modele_id' => $modeleId]))->assertRedirect();
            $facture = Facture::latest('id')->first();

            $this->get(route('factures.pdf', $facture))->assertOk();
        }
    }
}
