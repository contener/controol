<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Boutique;
use App\Models\EssaiUtilisateur;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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
     * $boutique : champs de la première boutique saisis dans le formulaire
     * d'inscription en étapes (déjà validés par CreateNewUser via ReglesBoutique,
     * logo/bannière en UploadedFile). Vide pour une inscription Google : repli
     * sur "Boutique de {nom}", à compléter ensuite depuis "Mes boutiques".
     */
    public function initialiser(User $user, array $boutique = []): void
    {
        $this->demarrerEssaiOuGratuit($user);
        app(InvitationBoutiqueService::class)->apresInscription($user);
        app(ParrainageService::class)->apresInscription($user);
        $this->creerBoutiqueInitiale($user, $boutique);
    }

    /**
     * L'inscription vaut création de la première boutique (2026-11-13) : plus
     * d'écran "Créez votre première boutique" après l'inscription -- l'utilisateur
     * arrive directement sur le tableau de bord de sa boutique, sur le web comme
     * sur mobile, y compris via Google. Il pourra ensuite en créer d'autres depuis
     * "Mes boutiques" (BoutiqueController, limite du plan). Créée après
     * l'abonnement (essai/gratuit), et suivie de apresCreationBoutique() comme une
     * création manuelle (journal d'invitation si l'inscription venait de la page
     * d'une autre boutique). Les comptes collaborateurs créés par un propriétaire
     * ne passent jamais par ici.
     */
    protected function creerBoutiqueInitiale(User $user, array $champs): void
    {
        $nom = trim((string) ($champs['nom'] ?? '')) ?: "Boutique de {$user->name}";

        $donnees = collect($champs)
            ->only([
                'description', 'categorie', 'adresse', 'ville', 'pays', 'telephone', 'whatsapp', 'email',
                'devise', 'taux_tva_defaut', 'nui', 'note_pied_facture', 'facebook_url', 'instagram_url', 'telegram_url',
            ])
            ->filter(fn ($valeur) => $valeur !== null && $valeur !== '')
            ->all();

        if (($champs['logo'] ?? null) instanceof UploadedFile) {
            $donnees['logo_path'] = $champs['logo']->store('boutiques/logos', 'public');
        }

        if (($champs['banniere'] ?? null) instanceof UploadedFile) {
            $donnees['banniere_path'] = $champs['banniere']->store('boutiques/bannieres', 'public');
        }

        $boutique = Boutique::create([
            'email' => $user->email,
            ...$donnees,
            'user_id' => $user->id,
            'nom' => $nom,
            'slug' => Boutique::genererSlugUnique($nom),
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
