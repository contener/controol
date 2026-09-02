<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDestinationSocialeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->currentBoutique !== null
            && $this->user()->planActif()?->publication_sociale === true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'lien' => ['required', 'url', 'max:500'],
            'type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
