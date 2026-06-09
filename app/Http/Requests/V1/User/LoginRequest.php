<?php

namespace App\Http\Requests\V1\User;

use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => 'required|string',
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'le mail ou le numéro de téléphone est requis.',
            'login.string' => 'le mail ou le numéro de téléphone doit être une chaîne de caractères.',
            'password.required' => 'le mot de passe est requis.',
            'password.string' => 'le mot de passe doit être une chaîne de caractères.',
            'password.min' => 'le mot de passe doit contenir au moins 8 caractères.',
        ];
    }

    public function prepareForValidation(): void
    {
        $login = $this->input('login');
        if ($login === null || ! is_string($login) || trim($login) === '') {
            return;
        }

        $login = trim($login);
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $this->merge(['login' => strtolower($login)]);
        } else {
            $this->merge(['login' => IvoryCoastPhone::normalize($login)]);
        }
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Les données fournies sont invalides.',
            'errors' => $validator->errors(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
