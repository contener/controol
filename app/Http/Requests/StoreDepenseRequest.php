<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Depense::class);
    }

    public function rules(): array
    {
        return [
            'categorie' => ['required', 'string', 'max:100'],
            'montant' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'date_depense' => ['required', 'date'],
        ];
    }
}
