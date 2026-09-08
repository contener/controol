<?php

namespace App\Services;

/**
 * Normalise un numéro de téléphone en format international pour servir de clé de
 * rapprochement fiable (dédoublonnage import, correspondance avec un compte utilisateur) —
 * ne devine jamais un numéro ambigu : le drapeau "valide" doit être vérifié par l'appelant et
 * affiché à l'administrateur pour correction manuelle plutôt que d'être corrigé silencieusement.
 */
class NormalisationTelephoneService
{
    private const INDICATIF_DEFAUT = '237'; // Cameroun, cohérent avec le reste de l'app (XAF, +237)

    /**
     * @return array{normalise: string, valide: bool}|null
     */
    public function normaliser(?string $numero): ?array
    {
        if (! $numero || trim($numero) === '') {
            return null;
        }

        $nettoye = preg_replace('/[^\d+]/', '', $numero);
        $nettoye = preg_replace('/^00/', '+', $nettoye);

        if (! str_starts_with($nettoye, '+')) {
            $nettoye = '+'.self::INDICATIF_DEFAUT.ltrim($nettoye, '0');
        }

        return [
            'normalise' => $nettoye,
            'valide' => (bool) preg_match('/^\+\d{8,15}$/', $nettoye),
        ];
    }
}
