<?php

namespace App\Http\Requests\V1\Salon;

use App\Enums\SalonCity;
use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UpdateSalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $salonId = $this->user()?->salon_id;

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100', 'regex:/^[\pL\pN\s\-\'’]+$/u'],
            'phone' => ['sometimes', 'nullable', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && ! IvoryCoastPhone::isValid((string) $value)) {
                    $fail('Le format du numéro de téléphone est invalide.');
                }
            }],
            'whatsapp_number' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! IvoryCoastPhone::isValid((string) $value)) {
                        $fail('Le format du numéro WhatsApp est invalide.');
                    }
                },
                Rule::unique('salons', 'whatsapp_number')->ignore($salonId),
            ],
            'city' => ['sometimes', 'string', Rule::in(SalonCity::values())],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Le nom du salon doit contenir au moins 2 caractères.',
            'name.max' => 'Le nom du salon ne peut pas dépasser 100 caractères.',
            'name.regex' => 'Le nom du salon contient des caractères non autorisés.',
            'whatsapp_number.unique' => 'Ce numéro WhatsApp est déjà associé à un salon.',
            'city.in' => 'La ville sélectionnée est invalide.',
            'address.max' => 'L\'adresse ne peut pas dépasser 255 caractères.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $payload['name'] = trim($this->input('name'));
        }

        if ($this->has('address') && is_string($this->input('address'))) {
            $payload['address'] = trim($this->input('address'));
        }

        foreach (['phone', 'whatsapp_number'] as $field) {
            if ($this->has($field) && is_string($this->input($field)) && $this->input($field) !== '') {
                $payload[$field] = IvoryCoastPhone::normalize($this->input($field));
            }
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
