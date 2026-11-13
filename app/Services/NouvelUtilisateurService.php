<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Boutique;
use App\Models\EssaiUtilisateur;
use App\Models\Plan;
use App\Models\User;

/**
 * Effets de bord communs à toute création de compte, quel que soit le point d'entrée
 * (formulaire email/mot de passe via CreateNewUser, ou connexion Google via
 * GoogleAuthController) : démarrage de l'essai/plan gratuit, capture d'une invitation
 * boutique et d'un parrainage en attente en session. Centralisé ici pour ne jamais
 * dupliquer cette logique entre les différents flux d'inscription.
 */
class NouvelUtilisateurService
{
    /**
     * $nomBoutique : nom saisi à l'inscription (champ facultatif du formulaire web
     * Register.vue et de l'app mobile) -- repli sur "Boutique de {nom}".
     */
    public function initialiser(User $user, ?string $nomBoutique = null): void
    {
        $this->demarrerEssaiOuGratuit($user);
        app(InvitationBoutiqueService::class)->apresInscription($user);
        app(ParrainageService::class)->apresInscription($user);
        $this->creerBoutiqueInitiale($user, $nomBoutique);
    }

    /**
     * L'inscription vaut création de la boutique (2026-11-13) : plus d'écran
     * "Créez votre première boutique" après l'inscription -- l'utilisateur arrive
     * directement sur le tableau de bord de sa boutique, sur le web comme sur
     * mobile, y compris via Google. Créée après l'abonnement (essai/gratuit) pour
     * respecter la limite de boutiques du plan, et suivie de
     * apresCreationBoutique() comme une création manuelle (journal d'invitation
     * si l'inscription venait de la page d'une autre boutique). Les comptes
     * collaborateurs créés par un propriétaire ne passent jamais par ici.
     */
    protected function creerBoutiqueInitiale(User $user, ?string $nomBoutique): void
    {
        $nom = trim((string) $nomBoutique) ?: "Boutique de {$user->name}";

        $boutique = Boutique::create([
            'user_id' => $user->id,
            'nom' => $nom,
            'slug' => Boutique::genererSlugUnique($nom),
            'email' => $user->email,
        ]);

        $user->switchBoutique($boutique);

        app(InvitationBoutiqueService::class)->apresCreationBoutique($user, $boutique);
    }

    /**
     * Demarre l'utilisateur sur un essai de 7 jours du plan Basique s'il existe,
     * sinon repli sur le plan Gratuit (comportement d'origine). Ne cree jamais les
     * deux abonnements "actif" en parallele : abonnementActif() les departagerait par
     * date_debut desc sur des valeurs quasi identiques (deux appels now() dans la meme
     * requete), ambigu. A l'expiration de l'essai (7 jours), la commande planifiee
     * existante abonnements:expirer fait deja automatiquement revenir l'utilisateur
     * au plan Gratuit -- aucune logique de repli supplementaire n'est necessaire ici.
     */
    protected function demarrerEssaiOuGratuit(User $user): void
    {
        $planBasique = Plan::where('code', 'basique')->first();

        if (! $planBasique) {
            $this->creerAbonnementGratuit($user);

            return;
        }

        $dateDebut = now();
        $dateFin = $dateDebut->copy()->addDays(7);

        $abonnement = Abonnement::create([
            'user_id' => $user->id,
            'plan_id' => $planBasique->id,
            'statut' => 'actif',
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);

        EssaiUtilisateur::create([
            'user_id' => $user->id,
            'abonnement_id' => $abonnement->id,
            'plan_id' => $planBasique->id,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'prix_promo' => 3500,
        ]);
    }

    /**
     * Repli utilise uniquement si le plan Basique n'existe pas en base.
     */
    protected function creerAbonnementGratuit(User $user): void
    {
        $planGratuit = Plan::where('code', 'gratuit')->first();

        if (! $planGratuit) {
            return;
        }

        Abonnement::create([
            'user_id' => $user->id,
            'plan_id' => $planGratuit->id,
            'statut' => 'actif',
            'date_debut' => now(),
            'date_fin' => null,
        ]);
    }
}
