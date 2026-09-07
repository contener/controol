<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationDepuisClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Conversation::class);
    }

    public function rules(): array
    {
        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where('boutique_id', $this->user()->currentBoutique?->id),
            ],
            'produit_id' => ['nullable', 'integer'],
            'contenu' => ['required', 'string', 'max:2000'],
        ];
    }
}
