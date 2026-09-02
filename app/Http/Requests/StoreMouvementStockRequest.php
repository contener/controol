<?php

namespace App\Http\Requests;

use App\Models\Produit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMouvementStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $produit = Produit::find($this->input('produit_id'));

        return $produit !== null && $this->user()->can('update', $produit);
    }

    public function rules(): array
    {
        $boutiqueId = $this->user()->currentBoutique->id;

        return [
            'produit_id' => [
                'required',
                Rule::exists('produits', 'id')->where('boutique_id', $boutiqueId)->where('gere_stock', true),
            ],
            'type' => ['required', 'in:entree,sortie,ajustement'],
            'quantite' => ['required', 'integer', 'min:1'],
            'motif' => ['nullable', 'string', 'max:255'],
        ];
    }
}
