<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CompresseImagesTeleversees;
use App\Models\Boutique;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ajout rapide d'un produit depuis le bouton flottant de la page boutique publique
 * (/boutique/{slug}) -- distinct de StoreProduitRequest qui, lui, résout toujours la
 * boutique via `currentBoutique` (le contexte "boutique sélectionnée" en session).
 * Ici la boutique vient du slug de l'URL, jamais de currentBoutique : un propriétaire
 * de plusieurs boutiques peut consulter la page publique de N'IMPORTE laquelle des
 * siennes sans que le produit atterrisse par erreur dans sa boutique "courante".
 */
class StoreProduitDepuisBoutiqueRequest extends FormRequest
{
    use CompresseImagesTeleversees;

    private ?Boutique $boutique = null;

    private bool $boutiqueResolue = false;

    public function authorize(): bool
    {
        return $this->boutique() !== null && $this->user()->id === $this->boutique()->user_id;
    }

    public function boutique(): ?Boutique
    {
        if (! $this->boutiqueResolue) {
            $this->boutique = Boutique::where('slug', $this->route('slug'))->where('statut', 'active')->first();
            $this->boutiqueResolue = true;
        }

        return $this->boutique;
    }

    protected function prepareForValidation(): void
    {
        $this->compresserImage('photo', 2048, forcerCarre: true);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:produit,service'],
            'nom' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix_vente' => ['required', 'numeric', 'min:0'],
            'unite' => ['required', 'string', 'max:50'],
            'categorie' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
