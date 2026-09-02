<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'in:fr,en'],
            'theme' => ['required', 'in:light,dark,system'],
        ];
    }
}
