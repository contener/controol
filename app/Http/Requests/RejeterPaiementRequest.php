<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejeterPaiementRequest extends FormRequest
{
    /**
     * Contrôle redondant avec le middleware 'admin.permission:paiements.refuser' de la
     * route (défense en profondeur) : même si la route était un jour mal protégée, cette
     * vérification bloque quand même la requête. hasAdminPermission() court-circuite sur
     * isSuperAdmin(), donc le Super Admin passe toujours ; un admin classique doit
     * détenir la permission 'paiements.refuser'.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAdminPermission('paiements.refuser') ?? false;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'max:1000'],
        ];
    }
}
