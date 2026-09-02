<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmerCampagneDestinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campagneDestination = $this->route('campagneDestination');

        return $campagneDestination !== null
            && $this->user()->id === $campagneDestination->campagne->boutique->user_id;
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', 'in:envoye,echec,non_autorise'],
        ];
    }
}
