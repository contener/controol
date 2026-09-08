<?php

namespace App\Observers;

use App\Models\Contact;
use App\Models\User;
use App\Services\NormalisationTelephoneService;

/**
 * Rapproche automatiquement un contact de prospection avec le compte CONTROOL qu'il vient de
 * créer (ou dont il vient de renseigner le numéro), dès l'inscription ou la mise à jour du
 * profil — sans jamais supprimer le contact ni écraser un statut commercial déjà plus avancé
 * (ex. "converti") par un retour en arrière vers "compte_cree".
 */
class UserObserver
{
    public function created(User $user): void
    {
        $this->rapprocher($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('whatsapp') || $user->wasChanged('telephone')) {
            $this->rapprocher($user);
        }
    }

    private function rapprocher(User $user): void
    {
        $service = app(NormalisationTelephoneService::class);

        foreach ([$user->whatsapp, $user->telephone] as $numero) {
            $normalise = $service->normaliser($numero)['normalise'] ?? null;

            if (! $normalise) {
                continue;
            }

            $contact = Contact::where('numero_normalise', $normalise)
                ->whereNull('utilisateur_id')
                ->first();

            if (! $contact) {
                continue;
            }

            $rangActuel = array_search($contact->statut_commercial, Contact::STATUTS_COMMERCIAUX, true);
            $rangCompteCree = array_search(Contact::STATUT_COMMERCIAL_COMPTE_CREE, Contact::STATUTS_COMMERCIAUX, true);

            $contact->update([
                'utilisateur_id' => $user->id,
                'lie_a' => now(),
                'statut_commercial' => $rangActuel !== false && $rangActuel >= $rangCompteCree
                    ? $contact->statut_commercial
                    : Contact::STATUT_COMMERCIAL_COMPTE_CREE,
            ]);

            return;
        }
    }
}
