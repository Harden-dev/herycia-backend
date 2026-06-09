<?php

namespace App\Http\Requests\V1\User;

use App\Enums\SalonStaffRole;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class CreateSalonEmployeeRequest extends FormRequest
{
    /** @return list<string> */
    public static function assignableRoles(): array
    {
        return [
            SalonStaffRole::Manager->value,
            SalonStaffRole::Stylist->value,
            SalonStaffRole::Receptionist->value,
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! IvoryCoastPhone::isValid((string) $value)) {
                    $fail('Le format du numéro de téléphone est invalide.');
                }
            }, 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', 'string', new Enum(SalonStaffRole::class), Rule::in(self::assignableRoles())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'role.required' => 'Le rôle est obligatoire.',
            'role.in' => 'Le rôle sélectionné est invalide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $payload['name'] = trim($this->input('name'));
        }

        if ($this->has('phone') && is_string($this->input('phone'))) {
            $payload['phone'] = IvoryCoastPhone::normalize($this->input('phone'));
        }

        if ($payload !== []) {
            $this->merge($payload);
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
