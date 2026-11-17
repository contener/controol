<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout / retrait manuel d'argent dans la caisse -- même accès que les Dépenses
 * (propriétaire, ou collaborateur avec la permission Dépenses).
 */
class MouvementCaisseManuelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->currentBoutique?->autoriseDepenses($this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'montant' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'motif' => ['required', 'string', 'max:200'],
        ];
    }

    public function attributes(): array
    {
        return ['montant' => 'montant', 'motif' => 'motif'];
    }
}
