<?php

namespace App\Http\Requests\V1\Salon;

use App\Enums\PlanCode;
use App\Enums\SalonCity;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SalonRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salon_name' => ['required', 'string', 'min:2', 'max:100', 'regex:/^[\pL\pN\s\-\'’]+$/u'],
            'city' => ['required', 'string', Rule::in(SalonCity::values())],
            'whatsapp_number' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! IvoryCoastPhone::isValid($value)) {
                    $fail('Le format du numéro WhatsApp est invalide.');
                }
            }, 'unique:salons,whatsapp_number'],
            'admin_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! IvoryCoastPhone::isValid($value)) {
                    $fail('Le format du numéro de téléphone est invalide.');
                }
            }, 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'plan_code' => [
                'required',
                'string',
                Rule::in(PlanCode::registerableValues()),
                Rule::exists('plans', 'code')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'salon_name.required' => 'Le nom du salon est obligatoire.',
            'salon_name.min' => 'Le nom du salon doit contenir au moins 2 caractères.',
            'salon_name.max' => 'Le nom du salon ne peut pas dépasser 100 caractères.',
            'salon_name.regex' => 'Le nom du salon contient des caractères non autorisés.',
            'city.required' => 'La ville est obligatoire.',
            'city.in' => 'La ville sélectionnée est invalide.',
            'whatsapp_number.required' => 'Le numéro WhatsApp est obligatoire.',
            'whatsapp_number.regex' => 'Le format du numéro WhatsApp est invalide.',
            'whatsapp_number.unique' => 'Ce numéro WhatsApp est déjà associé à un salon.',
            'admin_name.required' => 'Le nom du gérant est obligatoire.',
            'admin_name.min' => 'Le nom du gérant doit contenir au moins 2 caractères.',
            'admin_name.max' => 'Le nom du gérant ne peut pas dépasser 100 caractères.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex' => 'Le format du numéro de téléphone est invalide.',
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.max' => 'Le mot de passe ne peut pas dépasser 255 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
            'plan_code.required' => 'Le plan choisi est obligatoire.',
            'plan_code.in' => 'Le plan sélectionné est invalide.',
            'plan_code.exists' => 'Le plan sélectionné n\'est plus disponible.',
        ];
    }

    public function attributes(): array
    {
        return [
            'salon_name' => 'nom du salon',
            'admin_name' => 'nom du gérant',
            'whatsapp_number' => 'numéro WhatsApp',
            'password_confirmation' => 'confirmation du mot de passe',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('salon_name') && is_string($this->input('salon_name'))) {
            $payload['salon_name'] = trim($this->input('salon_name'));
        }

        if ($this->has('admin_name') && is_string($this->input('admin_name'))) {
            $payload['admin_name'] = trim($this->input('admin_name'));
        }

        foreach (['whatsapp_number', 'phone'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $payload[$field] = IvoryCoastPhone::normalize($this->input($field));
            }
        }

        if ($this->has('plan_code') && is_string($this->input('plan_code'))) {
            $payload['plan_code'] = strtolower(trim($this->input('plan_code')));
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
