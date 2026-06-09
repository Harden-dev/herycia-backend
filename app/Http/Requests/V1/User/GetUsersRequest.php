<?php

namespace App\Http\Requests\V1\User;

use Illuminate\Foundation\Http\FormRequest;

class GetUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'Le numéro de page doit être un entier.',
            'page.min' => 'Le numéro de page doit être supérieur ou égal à 1.',
            'per_page.integer' => 'Le nombre d\'éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d\'éléments par page doit être supérieur ou égal à 1.',
            'per_page.max' => 'Le nombre d\'éléments par page ne peut pas dépasser 100.',
            'search.string' => 'Le terme de recherche doit être une chaîne de caractères.',
            'search.max' => 'Le terme de recherche ne peut pas dépasser 255 caractères.',
            'is_active.boolean' => 'Le statut actif doit être un booléen.',
        ];
    }
}

