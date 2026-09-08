<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappContactLog;

/**
 * Logique de relance WhatsApp partagée entre la gestion des utilisateurs CONTROOL
 * (Admin\UtilisateurController) et la base de contacts de prospection
 * (Admin\ContactController) — un seul historique (WhatsappContactLog), un seul générateur de
 * lien. Le lien wa.me est toujours construit ici, côté serveur, jamais en JavaScript : un
 * admin disposant de la permission "contacter" mais pas de la permission "voir" peut relancer
 * sans que son navigateur ne reçoive jamais le numéro brut.
 */
class WhatsappRelanceService
{
    /**
     * @param  array{user_id: int}|array{contact_id: int}  $cible  Exactement une des deux clés.
     */
    public function contacter(string $numero, string $message, ?string $modeleCle, array $cible, User $admin): WhatsappContactLog
    {
        return WhatsappContactLog::create([
            ...$cible,
            'admin_id' => $admin->id,
            'numero_whatsapp' => $numero,
            'message' => $message,
            'modele_cle' => $modeleCle,
            'ouvert_a' => now(),
        ]);
    }

    public function lien(string $numero, string $message): string
    {
        $numeroPropre = ltrim(preg_replace('/[^\d+]/', '', $numero), '+');

        return "https://wa.me/{$numeroPropre}?text=".rawurlencode($message);
    }
}
