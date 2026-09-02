<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:255'],
            'numero_fiscal' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'etiquette' => ['required', 'in:prospect,client'],
        ];
    }
}
