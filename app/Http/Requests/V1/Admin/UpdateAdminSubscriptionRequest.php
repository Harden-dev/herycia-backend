<?php

namespace App\Http\Requests\V1\Admin;

use App\Enums\SubscriptionStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UpdateAdminSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['sometimes', 'uuid', 'exists:plans,id'],
            'status' => ['sometimes', 'string', Rule::in(array_map(fn (SubscriptionStatus $status) => $status->value, SubscriptionStatus::cases()))],
            'started_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'is_trial' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.uuid' => 'Le plan sélectionné est invalide.',
            'plan_id.exists' => 'Le plan sélectionné est introuvable.',
            'status.in' => 'Le statut sélectionné est invalide.',
            'started_at.date' => 'La date de début est invalide.',
            'ends_at.date' => 'La date de fin est invalide.',
            'trial_ends_at.date' => 'La date de fin d\'essai est invalide.',
            'is_trial.boolean' => 'Le champ essai doit être un booléen.',
        ];
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
