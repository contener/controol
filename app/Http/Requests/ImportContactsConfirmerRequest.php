<?php

namespace App\Http\Requests;

use App\Support\ContactColonnesExcel;
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
        $regles = [
            'mapping' => ['required', 'array'],
            'strategie_doublon' => ['required', Rule::in(['ignorer', 'mettre_a_jour'])],
            'lignes_ignorees' => ['nullable', 'array'],
            'lignes_ignorees.*' => ['integer'],
        ];

        foreach (ContactColonnesExcel::champsSimples() as $champ) {
            $regles["mapping.{$champ}"] = ['nullable', 'string'];
        }

        return $regles;
    }
}
