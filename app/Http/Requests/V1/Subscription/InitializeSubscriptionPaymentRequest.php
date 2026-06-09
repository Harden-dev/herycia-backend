<?php

namespace App\Http\Requests\V1\Subscription;

use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class InitializeSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
            'plan_code.required' => 'Le plan à souscrire est obligatoire.',
            'plan_code.in' => 'Le plan sélectionné est invalide.',
            'plan_code.exists' => 'Le plan sélectionné n\'est plus disponible.',
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
