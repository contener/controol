<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DemarrerCampagneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->currentBoutique !== null
            && $this->user()->planActif()?->publication_sociale === true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'intervalle_secondes' => ['required', 'integer', 'min:5', 'max:3600'],
        ];
    }
}
