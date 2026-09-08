<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportContactsConfirmerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAdminPermission('contacts.importer');
    }

    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.nom' => ['nullable', 'string'],
            'mapping.telephone' => ['nullable', 'string'],
            'mapping.whatsapp' => ['nullable', 'string'],
            'mapping.email' => ['nullable', 'string'],
            'mapping.ville' => ['nullable', 'string'],
            'mapping.entreprise' => ['nullable', 'string'],
            'mapping.categorie' => ['nullable', 'string'],
            'mapping.source' => ['nullable', 'string'],
            'mapping.notes' => ['nullable', 'string'],
            'strategie_doublon' => ['required', Rule::in(['ignorer', 'mettre_a_jour'])],
            'lignes_ignorees' => ['nullable', 'array'],
            'lignes_ignorees.*' => ['integer'],
        ];
    }
}
