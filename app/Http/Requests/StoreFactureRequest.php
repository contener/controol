<?php

namespace App\Http\Requests;

use App\Models\Facture;
use App\Policies\FacturePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactureRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', Facture::class)) {
            return false;
        }

        return app(FacturePolicy::class)->useModele($this->user(), (int) $this->input('modele_id', 1));
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
            'garantie' => ['nullable', 'string', 'max:1000'],
            'statut' => ['nullable', 'in:brouillon,envoyee,payee,annulee'],
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
