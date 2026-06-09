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

class UpdateSalonEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'phone' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! IvoryCoastPhone::isValid((string) $value)) {
                        $fail('Le format du numéro de téléphone est invalide.');
                    }
                },
                Rule::unique('users', 'phone')->ignore($employeeId),
            ],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employeeId)],
            'password' => ['sometimes', 'string', 'min:8', 'max:255', 'confirmed'],
            'password_confirmation' => ['required_with:password', 'string'],
            'role' => ['sometimes', 'string', new Enum(SalonStaffRole::class), Rule::in(CreateSalonEmployeeRequest::assignableRoles())],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
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
