<?php

namespace App\Http\Requests;

use App\Policies\FacturePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $facture = $this->route('facture');

        if (! $this->user()->can('update', $facture) || ! $facture->estModifiable()) {
            return false;
        }

        $modeleId = $this->has('modele_id') ? (int) $this->input('modele_id') : $facture->modele_id->value;

        return app(FacturePolicy::class)->useModele($this->user(), $modeleId, $facture);
    }

    public function rules(): array
    {
        $boutiqueId = $this->user()->currentBoutique->id;

        return [
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('boutique_id', $boutiqueId),
            ],
            'date_emission' => ['required', 'date'],
            'date_echeance' => ['nullable', 'date', 'after_or_equal:date_emission'],
            'remise' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'modele_id' => ['nullable', 'integer', 'between:1,10'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => [
                'nullable',
                Rule::exists('produits', 'id')->where('boutique_id', $boutiqueId),
            ],
            'lignes.*.designation' => ['required', 'string', 'max:255'],
            'lignes.*.description' => ['nullable', 'string'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0.01'],
            'lignes.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
            'lignes.*.tva_taux' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lignes.*.remise_ligne' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
