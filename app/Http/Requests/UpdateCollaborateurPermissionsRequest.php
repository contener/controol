<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidePermissionsCollaborateur;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Le propriétaire ajuste les permissions d'un collaborateur déjà créé (voir
 * Boutique::PERMISSIONS_COLLABORATEUR) -- même contrôle d'autorisation strict que
 * StoreCollaborateurRequest (propriétaire uniquement, jamais un collaborateur).
 */
class UpdateCollaborateurPermissionsRequest extends FormRequest
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
        return $this->reglesPermissions();
    }
}
