<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ResetAdminUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.string' => 'Le nouveau mot de passe doit être une chaîne de caractères.',
            'new_password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'new_password.max' => 'Le nouveau mot de passe ne peut pas dépasser 255 caractères.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('new_password') && $this->input('new_password') === '') {
            $this->merge(['new_password' => null]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les données fournies sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
