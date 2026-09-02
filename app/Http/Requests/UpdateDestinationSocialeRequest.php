<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDestinationSocialeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('destinations_sociale'));
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
