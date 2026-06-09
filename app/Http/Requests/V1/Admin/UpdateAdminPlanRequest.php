<?php

namespace App\Http\Requests\V1\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UpdateAdminPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'code' => ['sometimes', 'string', 'min:2', 'max:50', 'alpha_dash', Rule::unique('plans', 'code')->ignore($planId)],
            'price_fcfa' => ['sometimes', 'integer', 'min:0'],
            'max_employees' => ['sometimes', 'integer', 'min:0'],
            'max_services' => ['sometimes', 'integer', 'min:0'],
            'has_online_booking' => ['sometimes', 'boolean'],
            'has_analytics' => ['sometimes', 'boolean'],
            'has_multi_branch' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Le nom du plan doit contenir au moins 2 caractères.',
            'name.max' => 'Le nom du plan ne peut pas dépasser 100 caractères.',
            'code.min' => 'Le code du plan doit contenir au moins 2 caractères.',
            'code.max' => 'Le code du plan ne peut pas dépasser 50 caractères.',
            'code.alpha_dash' => 'Le code du plan ne doit contenir que des lettres, chiffres, tirets et underscores.',
            'code.unique' => 'Ce code de plan existe déjà.',
            'price_fcfa.integer' => 'Le prix doit être un nombre entier.',
            'price_fcfa.min' => 'Le prix doit être supérieur ou égal à 0.',
            'max_employees.integer' => 'Le nombre maximum d\'employés doit être un nombre entier.',
            'max_employees.min' => 'Le nombre maximum d\'employés doit être supérieur ou égal à 0.',
            'max_services.integer' => 'Le nombre maximum de services doit être un nombre entier.',
            'max_services.min' => 'Le nombre maximum de services doit être supérieur ou égal à 0.',
            'has_online_booking.boolean' => 'Le champ réservation en ligne doit être un booléen.',
            'has_analytics.boolean' => 'Le champ analytics doit être un booléen.',
            'has_multi_branch.boolean' => 'Le champ multi-agence doit être un booléen.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $payload['name'] = trim($this->input('name'));
        }

        if ($this->has('code') && is_string($this->input('code'))) {
            $payload['code'] = strtoupper(trim($this->input('code')));
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
