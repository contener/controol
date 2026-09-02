<?php

namespace App\Actions\Fortify;

use App\Models\Abonnement;
use App\Models\Plan;
use App\Models\User;
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
                $this->creerAbonnementGratuit($user);
            });
        });
    }

    /**
     * Démarre l'utilisateur sur le plan gratuit dès l'inscription.
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
