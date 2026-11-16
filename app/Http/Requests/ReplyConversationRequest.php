<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // "conversationGeree" : route API de la messagerie unifiée (toutes les boutiques gérées).
        return $this->user()->can('reply', $this->route('conversation') ?? $this->route('conversationGeree'));
    }

    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
        ];
    }
}
