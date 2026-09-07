<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyGuestConversationRequest extends FormRequest
{
    // Public — l'accès à CETTE conversation précise est vérifié via VisiteurIdentiteService
    // dans le contrôleur (cookie ou compte), pas ici : une FormRequest n'a pas accès au
    // paramètre de route sans binding implicite, qu'on évite volontairement (voir §0 du plan
    // — jamais type-hinter Conversation sur une route accessible à un visiteur non lié).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
        ];
    }
}
