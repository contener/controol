<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CompresseImagesTeleversees;
use App\Support\ReglesBoutique;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBoutiqueRequest extends FormRequest
{
    use CompresseImagesTeleversees;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boutique'));
    }

    protected function prepareForValidation(): void
    {
        $this->compresserImage('logo', 2048);
        $this->compresserImage('banniere', 4096);
    }

    public function rules(): array
    {
        return ReglesBoutique::champs();
    }
}
