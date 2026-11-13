<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\NouvelUtilisateurService;
use App\Support\ReglesBoutique;
use Illuminate\Http\UploadedFile;
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
     * L'inscription est aussi la création de la première boutique (formulaire en
     * étapes Register.vue / écran d'inscription mobile, 2026-11-13) : les champs de
     * la boutique arrivent imbriqués sous "boutique.*" et sont validés par les
     * mêmes règles que la création depuis "Mes boutiques" (ReglesBoutique), logo
     * et bannière compressés de la même façon avant validation.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input['boutique'] = $this->compresserImages(is_array($input['boutique'] ?? null) ? $input['boutique'] : []);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
            'boutique' => ['required', 'array'],
            ...ReglesBoutique::champs('boutique.'),
        ], [], ReglesBoutique::libelles('boutique.'))->validate();

        return DB::transaction(function () use ($input) {
            return tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
            ]), fn (User $user) => app(NouvelUtilisateurService::class)->initialiser($user, $input['boutique']));
        });
    }

    private function compresserImages(array $boutique): array
    {
        $compression = app(ImageCompressionService::class);

        foreach (['logo' => ReglesBoutique::TAILLE_LOGO_KO, 'banniere' => ReglesBoutique::TAILLE_BANNIERE_KO] as $champ => $tailleKo) {
            if (($boutique[$champ] ?? null) instanceof UploadedFile) {
                $boutique[$champ] = $compression->compresserSiNecessaire($boutique[$champ], $tailleKo * 1024);
            }
        }

        return $boutique;
    }
}
