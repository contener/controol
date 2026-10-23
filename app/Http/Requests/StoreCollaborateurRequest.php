<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidePermissionsCollaborateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Même schéma que StoreAdministrateurRequest (Super Admin créant un compte Admin) --
 * ici le propriétaire d'une boutique créant un compte collaborateur. Propriétaire
 * strict (jamais un collaborateur même avec accès complet par ailleurs, pour éviter
 * toute chaîne d'escalade de privilège) + plan personnel_boutique requis.
 *
 * Les 7 permissions (voir Boutique::PERMISSIONS_COLLABORATEUR) sont optionnelles ici :
 * absentes ou décochées, elles restent à false (défaut colonne) -- jamais d'accès
 * automatique, le propriétaire doit cocher explicitement ce qu'il accorde.
 */
class StoreCollaborateurRequest extends FormRequest
{
    use ValidePermissionsCollaborateur;

    public function authorize(): bool
    {
        $boutique = $this->user()?->currentBoutique;

        if (! $boutique || $boutique->user_id !== $this->user()->id) {
            return false;
        }

        return (bool) $this->user()->planActif()?->personnel_boutique;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', Password::default()],
            ...$this->reglesPermissions(),
        ];
    }
}
