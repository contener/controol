<?php

namespace App\Http\Requests;

use App\Support\WhatsappModeles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsappContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAdminPermission('whatsapp.contacter');
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'modele_cle' => ['nullable', 'string', Rule::in(WhatsappModeles::cles())],
        ];
    }
}
