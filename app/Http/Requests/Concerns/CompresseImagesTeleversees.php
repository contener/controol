<?php

namespace App\Http\Requests\Concerns;

use App\Services\ImageCompressionService;

/**
 * À utiliser dans prepareForValidation() d'un FormRequest, avant les règles 'image'/'max:'
 * — une image dépassant la limite est automatiquement compressée sous la limite plutôt
 * que rejetée. Voir App\Services\ImageCompressionService pour la logique de compression.
 */
trait CompresseImagesTeleversees
{
    protected function compresserImage(string $champ, int $tailleMaxKo, bool $forcerCarre = false): void
    {
        if ($this->hasFile($champ)) {
            $compressee = app(ImageCompressionService::class)->compresserSiNecessaire($this->file($champ), $tailleMaxKo * 1024, $forcerCarre);
            $this->files->set($champ, $compressee);

            // allFiles()/file() mémorisent leur résultat dans Request::$convertedFiles dès
            // le premier appel (voir hasFile() ci-dessus, qui vient d'en déclencher un) — sans
            // réinitialiser ce cache, la validation continuerait de lire le fichier d'origine.
            $this->convertedFiles = null;
        }
    }
}
