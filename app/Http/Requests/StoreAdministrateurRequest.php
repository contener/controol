<?php

namespace App\Http\Requests;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAdministrateurRequest extends FormRequest
{
    /**
     * Contrôle redondant avec le middleware 'super_admin' de la route
     * (/admin/administrateurs/*, voir routes/web.php) — défense en profondeur, même
     * schéma que RejeterPaiementRequest.
     *
     * IMPORTANT : ni ici ni dans rules() il n'existe de règle acceptant un champ 'role' —
     * le contrôleur fixe role=User::ROLE_ADMIN en dur. Aucune requête, même forgée, ne
     * peut faire passer un compte en super_admin par ce chemin (RULE 6).
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', Password::default()],
            'admin_role_label' => ['required', 'string', Rule::in(AdminPermissions::libellesRoles())],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::toutesLesCles())],
        ];
    }
}
