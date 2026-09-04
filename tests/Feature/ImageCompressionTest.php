<?php

namespace Tests\Feature;

use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesBoutique;
use Tests\TestCase;

class ImageCompressionTest extends TestCase
{
    use CreatesBoutique, RefreshDatabase;

    /**
     * Génère une vraie image JPEG "difficile à compresser" (bruit aléatoire pixel par
     * pixel — un contenu uni/dégradé compresserait trop bien pour dépasser 2 Mo même en
     * grandes dimensions). Retourne un UploadedFile utilisable dans une requête de test.
     */
    private function imageBruitee(int $largeur, int $hauteur, int $qualiteInitiale = 100): UploadedFile
    {
        $image = imagecreatetruecolor($largeur, $hauteur);
        mt_srand(42); // déterministe, pour un test reproductible
        for ($x = 0; $x < $largeur; $x += 2) {
            for ($y = 0; $y < $hauteur; $y += 2) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }

        $chemin = tempnam(sys_get_temp_dir(), 'bruit_').'.jpg';
        imagejpeg($image, $chemin, $qualiteInitiale);
        imagedestroy($image);

        return new UploadedFile($chemin, 'photo.jpg', 'image/jpeg', null, true);
    }

    // Preuve que le fichier généré est bien un cas de test valide (> 2 Mo, vraie image).
    private function assertPreconditionFichierVolumineux(UploadedFile $fichier, int $minimumOctets): void
    {
        $this->assertGreaterThan($minimumOctets, $fichier->getSize(), 'Précondition de test invalide : le fichier généré doit dépasser la limite pour tester la compression.');
        $this->assertNotFalse(@getimagesize($fichier->getRealPath()), 'Précondition de test invalide : le fichier généré doit être une image réelle.');
    }

    public function test_service_compresses_a_large_image_under_the_target_size(): void
    {
        $fichier = $this->imageBruitee(1600, 1200);
        $this->assertPreconditionFichierVolumineux($fichier, 2 * 1024 * 1024);

        $resultat = app(ImageCompressionService::class)->compresserSiNecessaire($fichier, 300 * 1024);

        $this->assertLessThanOrEqual(300 * 1024, $resultat->getSize());
        $this->assertNotFalse(@getimagesize($resultat->getRealPath()), 'Le fichier compressé doit rester une image valide.');
    }

    public function test_service_does_not_touch_a_file_already_under_the_limit(): void
    {
        $fichier = UploadedFile::fake()->image('petite.jpg', 100, 100);
        $tailleOriginale = $fichier->getSize();

        $resultat = app(ImageCompressionService::class)->compresserSiNecessaire($fichier, 2 * 1024 * 1024);

        $this->assertSame($fichier, $resultat, 'Un fichier déjà sous la limite ne doit jamais être retouché.');
        $this->assertSame($tailleOriginale, $resultat->getSize());
    }

    // TEST bout en bout : un logo de boutique > 2 Mo n'est plus rejeté, il est accepté
    // (compressé automatiquement) — c'est le comportement demandé, remplaçant le blocage.
    public function test_oversized_boutique_logo_is_compressed_instead_of_rejected(): void
    {
        Storage::fake('public');
        $user = $this->creerUtilisateurAvecBoutique();
        $boutique = $user->currentBoutique;

        $logo = $this->imageBruitee(1600, 1200);
        $this->assertPreconditionFichierVolumineux($logo, 2 * 1024 * 1024);

        $response = $this->actingAs($user)->put("/boutiques/{$boutique->id}", [
            'nom' => $boutique->nom,
            'devise' => 'XAF',
            'logo' => $logo,
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('logo');

        $boutique->refresh();
        $this->assertNotNull($boutique->logo_path);
        Storage::disk('public')->assertExists($boutique->logo_path);
        $this->assertLessThanOrEqual(2 * 1024 * 1024, Storage::disk('public')->size($boutique->logo_path));
    }
}
