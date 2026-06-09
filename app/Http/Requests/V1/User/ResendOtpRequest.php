<?php

namespace App\Http\Requests\V1\User;

use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class ResendOtpRequest extends FormRequest
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
            'login' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'L\'email ou le téléphone est requis.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $login = $this->input('login');
        if ($login === null || ! is_string($login) || trim($login) === '') {
            return;
        }

        $login = trim($login);
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $this->merge(['login' => strtolower($login)]);
        } else {
            $this->merge(['login' => PhoneNormalizer::toE164($login)]);
        }
    }
}
