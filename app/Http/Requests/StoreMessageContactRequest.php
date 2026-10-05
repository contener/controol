<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAdminPermission('notifications.envoyer');
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
