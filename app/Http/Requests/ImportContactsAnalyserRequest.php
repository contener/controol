<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportContactsAnalyserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAdminPermission('contacts.importer');
    }

    public function rules(): array
    {
        // Validation par extension déclarée plutôt que par détection de contenu (mimes:) :
        // un CSV texte brut est souvent détecté comme text/plain par le sniffing PHP, ce qui
        // rejetterait à tort des fichiers valides — acceptable ici vu le public restreint
        // (action admin protégée par permission, jamais un envoi public non authentifié).
        return [
            'fichier' => ['required', 'file', File::default()->extensions(['xlsx', 'xls', 'csv']), 'max:10240'],
        ];
    }
}
