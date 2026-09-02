<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejeterPaiementRequest extends FormRequest
{
    /**
     * Contrôle redondant avec le middleware 'super_admin' de la route (défense en
     * profondeur) : même si la route était un jour mal protégée, cette vérification
     * bloque quand même la requête.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'motif' => ['required', 'string', 'max:1000'],
        ];
    }
}
