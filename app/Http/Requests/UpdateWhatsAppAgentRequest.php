<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->currentBoutique !== null;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'langue' => ['required', 'string', 'max:10'],
            'personnalite' => ['nullable', 'string', 'max:255'],
            'ton' => ['nullable', 'string', 'max:255'],
            'message_accueil' => ['nullable', 'string', 'max:1000'],
            'message_hors_horaires' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
