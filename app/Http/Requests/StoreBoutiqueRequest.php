<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CompresseImagesTeleversees;
use Illuminate\Foundation\Http\FormRequest;

class StoreBoutiqueRequest extends FormRequest
{
    use CompresseImagesTeleversees;

    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Boutique::class);
    }

    protected function prepareForValidation(): void
    {
        $this->compresserImage('logo', 2048);
        $this->compresserImage('banniere', 4096);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categorie' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'devise' => ['nullable', 'string', 'max:3'],
            'taux_tva_defaut' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nui' => ['nullable', 'string', 'max:50'],
            'note_pied_facture' => ['nullable', 'string', 'max:1000'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banniere' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
