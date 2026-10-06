<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageContactMassifRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAdminPermission('notifications.envoyer');
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'utilisateur_ids' => ['required', 'array', 'min:1', 'max:500'],
            'utilisateur_ids.*' => ['integer'],
        ];
    }
}
