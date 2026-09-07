<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class ReplyConversationVisiteurRequest extends FormRequest
{
    // La route ne fait volontairement pas de binding implicite de Conversation (le scope
    // global de BelongsToBoutique filtrerait par la boutique COURANTE de l'utilisateur, pas
    // par les conversations où il est le VISITEUR — voir §0 du plan) : on résout donc la
    // conversation nous-mêmes ici pour vérifier la permission.
    public function authorize(): bool
    {
        $conversation = Conversation::withoutGlobalScopes()->find($this->route('conversation'));

        return $conversation !== null && $this->user()->can('repondreCommeVisiteur', $conversation);
    }

    public function rules(): array
    {
        return [
            'contenu' => ['required', 'string', 'max:2000'],
        ];
    }
}
