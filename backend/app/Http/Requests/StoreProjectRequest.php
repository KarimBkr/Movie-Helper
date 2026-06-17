<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'type'  => ['required', 'string', 'in:film,serie,court_metrage,pilote,autre'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max'      => 'Le titre ne peut pas dépasser 255 caractères.',
            'type.required'  => 'Le type de projet est obligatoire.',
            'type.in'        => 'Type invalide.',
        ];
    }
}
