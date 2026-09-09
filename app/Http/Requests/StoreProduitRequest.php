<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CompresseImagesTeleversees;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProduitRequest extends FormRequest
{
    use CompresseImagesTeleversees;

    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Produit::class);
    }

    protected function prepareForValidation(): void
    {
        $this->compresserImage('photo', 2048, forcerCarre: true);
    }

    public function rules(): array
    {
        $boutiqueId = $this->user()->currentBoutique->id;

        return [
            'type' => ['required', 'in:produit,service'],
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reference' => [
                'nullable', 'string', 'max:100',
                Rule::unique('produits', 'reference')->where('boutique_id', $boutiqueId),
            ],
            'prix_achat' => ['nullable', 'numeric', 'min:0'],
            'prix_vente' => ['required', 'numeric', 'min:0'],
            'unite' => ['required', 'string', 'max:50'],
            'tva_taux' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gere_stock' => ['required_if:type,produit', 'boolean'],
            'quantite_stock' => ['nullable', 'integer', 'min:0'],
            'seuil_alerte' => ['nullable', 'integer', 'min:0'],
            'actif' => ['nullable', 'boolean'],
            'promotion_prix' => ['nullable', 'numeric', 'min:0'],
            'categorie' => ['nullable', 'string', 'max:255'],
            'mini_characteristics' => ['nullable', 'string', 'max:250'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
