<?php

namespace App\Actions\Fortify;

use App\Models\Abonnement;
use App\Models\EssaiUtilisateur;
use App\Models\Plan;
use App\Models\User;
use App\Services\InvitationBoutiqueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            return tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]), function (User $user) {
                $this->demarrerEssaiOuGratuit($user);
                app(InvitationBoutiqueService::class)->apresInscription($user);
            });
        });
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
