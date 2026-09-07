<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Comprime automatiquement une image téléversée si elle dépasse la taille maximale
 * autorisée pour son champ, plutôt que de bloquer l'envoi avec une erreur de validation
 * (ex. une photo de 5 Mo est réduite sous 2 Mo automatiquement). Réduit d'abord la
 * qualité JPEG/WebP, puis si nécessaire les dimensions — jamais les deux à l'excès, on
 * s'arrête dès que la taille cible est atteinte pour préserver le maximum de qualité.
 *
 * Ne modifie jamais un fichier déjà sous la limite. Si GD est indisponible ou le format
 * n'est pas pris en charge (rare : gif, bmp...), le fichier original est retourné tel
 * quel — la règle de validation 'max:' habituelle s'applique alors en repli sûr.
 *
 * Avec $forcerCarre, recadre aussi l'image au centre sur un carré (plus petit côté) avant
 * la compression — utilisé pour les photos de produits, dont les dimensions d'origine
 * varient d'un produit à l'autre et cassent l'alignement des grilles d'affichage.
 */
class ImageCompressionService
{
    private const LARGEUR_MINIMALE = 300;

    public function compresserSiNecessaire(UploadedFile $fichier, int $tailleMaxOctets, bool $forcerCarre = false): UploadedFile
    {
        if ($forcerCarre && extension_loaded('gd')) {
            $fichier = $this->recadrerEnCarre($fichier) ?? $fichier;
        }

        if ($fichier->getSize() <= $tailleMaxOctets || ! extension_loaded('gd')) {
            return $fichier;
        }

        $mime = $fichier->getMimeType();
        $image = $this->charger($fichier->getRealPath(), $mime);

        if (! $image) {
            return $fichier;
        }

        $extension = $fichier->getClientOriginalExtension() ?: 'jpg';
        $cheminTemp = tempnam(sys_get_temp_dir(), 'img_compresse_').'.'.$extension;

        // 1) JPEG/WebP : d'abord on baisse la qualité, moins destructeur qu'une réduction
        //    de dimensions pour un même gain de poids.
        if (in_array($mime, ['image/jpeg', 'image/webp'], true)) {
            for ($qualite = 80; $qualite >= 35; $qualite -= 15) {
                $this->encoder($image, $mime, $cheminTemp, $qualite);
                clearstatcache(true, $cheminTemp);
                if (filesize($cheminTemp) <= $tailleMaxOctets) {
                    imagedestroy($image);

                    return new UploadedFile($cheminTemp, $fichier->getClientOriginalName(), $mime, null, true);
                }
            }
        }

        // 2) Sinon (ou si la qualité seule ne suffit pas) : réduction progressive des
        //    dimensions, jusqu'à la taille cible ou une largeur plancher.
        $largeur = imagesx($image);
        $tailleActuelle = PHP_INT_MAX;

        while ($tailleActuelle > $tailleMaxOctets && $largeur > self::LARGEUR_MINIMALE) {
            $largeur = (int) round($largeur * 0.85);
            $hauteur = (int) round(imagesy($image) * ($largeur / imagesx($image)));

            $redimensionnee = imagescale($image, $largeur, $hauteur);
            imagedestroy($image);
            $image = $redimensionnee;

            $this->encoder($image, $mime, $cheminTemp, 70);
            clearstatcache(true, $cheminTemp);
            $tailleActuelle = filesize($cheminTemp);
        }

        imagedestroy($image);

        return new UploadedFile($cheminTemp, $fichier->getClientOriginalName(), $mime, null, true);
    }

    // Recadrage centré au plus petit côté (largeur ou hauteur) — jamais d'étirement, la
    // photo garde ses proportions sur la zone conservée. Retourne null si l'image est déjà
    // carrée ou illisible, pour laisser l'appelant conserver le fichier d'origine tel quel.
    private function recadrerEnCarre(UploadedFile $fichier): ?UploadedFile
    {
        $mime = $fichier->getMimeType();
        $image = $this->charger($fichier->getRealPath(), $mime);

        if (! $image) {
            return null;
        }

        $largeur = imagesx($image);
        $hauteur = imagesy($image);

        if ($largeur === $hauteur) {
            imagedestroy($image);

            return null;
        }

        $cote = min($largeur, $hauteur);
        $x = (int) (($largeur - $cote) / 2);
        $y = (int) (($hauteur - $cote) / 2);

        $carre = imagecreatetruecolor($cote, $cote);

        if ($mime === 'image/png') {
            imagealphablending($carre, false);
            imagesavealpha($carre, true);
            imagefilledrectangle($carre, 0, 0, $cote, $cote, imagecolorallocatealpha($carre, 0, 0, 0, 127));
        }

        imagecopy($carre, $image, 0, 0, $x, $y, $cote, $cote);
        imagedestroy($image);

        $extension = $fichier->getClientOriginalExtension() ?: 'jpg';
        $cheminTemp = tempnam(sys_get_temp_dir(), 'img_carre_').'.'.$extension;
        $this->encoder($carre, $mime, $cheminTemp, 90);
        imagedestroy($carre);

        return new UploadedFile($cheminTemp, $fichier->getClientOriginalName(), $mime, null, true);
    }

    private function charger(string $chemin, ?string $mime)
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($chemin),
            'image/png' => @imagecreatefrompng($chemin),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($chemin) : null,
            default => null,
        };
    }

    private function encoder($image, string $mime, string $chemin, int $qualite): void
    {
        if ($mime === 'image/png') {
            imagesavealpha($image, true);
            // Le niveau de compression PNG (0-9) n'a qu'un effet limité sur une photo,
            // mais reste sans perte — un niveau élevé fixe suffit, la qualité JPEG/WebP
            // ci-dessus est le principal levier ; sinon on passe directement aux dimensions.
            imagepng($image, $chemin, 8);

            return;
        }

        if ($mime === 'image/webp' && function_exists('imagewebp')) {
            imagewebp($image, $chemin, $qualite);

            return;
        }

        imagejpeg($image, $chemin, $qualite);
    }
}
