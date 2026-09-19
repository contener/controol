<?php

namespace App\Services;

use App\Models\Boutique;
use App\Models\Suivi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Fait traverser la boutique d'origine d'une invitation (?boutique=slug&suivre=0|1,
 * capturée en session par CaptureInvitationBoutique) à travers l'inscription puis,
 * le cas échéant, la création de boutique -- ces deux étapes sont séparées par une
 * redirection (EnsureBoutiqueSelected) qui ne préserve pas la query string, d'où le
 * passage par la session plutôt qu'un champ de formulaire.
 */
class InvitationBoutiqueService
{
    public function apresInscription(User $user): void
    {
        $boutique = $this->resoudreBoutiqueOrigine($user);

        if (! $boutique) {
            return;
        }

        $this->journaliser($boutique, $user->id, 'compte_cree');

        if (Session::get('invitation_boutique_suivre')) {
            $this->creerSuivi($boutique, $user);
        }
    }

    public function apresCreationBoutique(User $user, Boutique $boutiqueCreee): void
    {
        $boutique = $this->resoudreBoutiqueOrigine($user, $boutiqueCreee);

        if ($boutique) {
            $this->journaliser($boutique, $user->id, 'boutique_creee');

            if (Session::get('invitation_boutique_suivre')) {
                $this->creerSuivi($boutique, $user);
            }
        }

        Session::forget(['invitation_boutique_slug', 'invitation_boutique_suivre']);
    }

    private function resoudreBoutiqueOrigine(User $user, ?Boutique $exclure = null): ?Boutique
    {
        $slug = Session::get('invitation_boutique_slug');

        if (! $slug) {
            return null;
        }

        $boutique = Boutique::where('slug', $slug)->first();

        if (! $boutique || $boutique->user_id === $user->id) {
            return null;
        }

        if ($exclure && $boutique->id === $exclure->id) {
            return null;
        }

        return $boutique;
    }

    private function creerSuivi(Boutique $boutique, User $user): void
    {
        $suivi = Suivi::firstOrNew(['boutique_id' => $boutique->id, 'user_id' => $user->id]);

        if ($suivi->exists && $suivi->estActif()) {
            return;
        }

        $suivi->notifications_actives = true;
        $suivi->abonne_a = now();
        $suivi->desabonne_a = null;
        $suivi->save();

        $this->journaliser($boutique, $user->id, 'abonnement_cree');
    }

    private function journaliser(Boutique $boutique, ?int $userId, string $type): void
    {
        try {
            DB::table('evenements_invitation_boutique')->insert([
                'boutique_id' => $boutique->id,
                'user_id' => $userId,
                'visiteur_token' => null,
                'type_evenement' => $type,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Journalisation best-effort : ne doit jamais faire échouer l'inscription
            // ou la création de boutique.
        }
    }
}
