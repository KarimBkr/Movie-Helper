<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'accès projet est vérifié dans le contrôleur via ProjectAccessService.
        return true;
    }

    public function rules(): array
    {
        return [
            // Final Draft = XML ; on borne la taille (10 Mo) et l'extension.
            'file' => ['required', 'file', 'max:10240', 'extensions:fdx'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Aucun fichier fourni.',
            'file.file' => 'Le fichier est invalide.',
            'file.max' => 'Le fichier dépasse la taille maximale autorisée (10 Mo).',
            'file.extensions' => 'Le fichier doit être un scénario Final Draft (.fdx).',
        ];
    }
}
