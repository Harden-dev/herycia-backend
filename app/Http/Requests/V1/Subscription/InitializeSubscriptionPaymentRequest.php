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
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * La clé d'idempotence est lue uniquement depuis l'en-tête (une valeur du corps est écrasée).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header(self::IDEMPOTENCY_HEADER)]);
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
            'idempotency_key' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{8,128}\z/'],
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
