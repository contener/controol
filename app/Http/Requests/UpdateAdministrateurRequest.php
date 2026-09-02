<?php

namespace App\Http\Requests;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdministrateurRequest extends FormRequest
{
    /**
     * Même garantie que StoreAdministrateurRequest : aucun champ 'role' n'est jamais
     * accepté ici. Le contrôleur ne touche jamais role sur ce chemin — un administrateur
     * reste 'admin' pour toujours via cette page (RULE 6).
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        $administrateurId = $this->route('administrateur')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($administrateurId)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', Password::default()],
            'admin_role_label' => ['required', 'string', Rule::in(AdminPermissions::libellesRoles())],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::toutesLesCles())],
        ];
    }
}
