<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    // Action publique, accessible à tout visiteur anonyme d'une boutique — aucune
    // authentification requise. Le rattachement à la boutique/au produit est vérifié et
    // fixé côté contrôleur, jamais depuis l'entrée utilisateur.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom_visiteur' => ['required', 'string', 'max:255'],
            'contact_visiteur' => ['nullable', 'string', 'max:255'],
            'contenu' => ['required', 'string', 'max:2000'],
            'produit_id' => ['nullable', 'integer'],
        ];
    }
}
