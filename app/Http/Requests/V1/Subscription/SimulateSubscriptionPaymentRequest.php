<?php

namespace App\Http\Requests\V1\Subscription;

use App\Enums\BillingPaymentMethod;
use App\Enums\PlanCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SimulateSubscriptionPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => [
                'required',
                'string',
                Rule::enum(BillingPaymentMethod::class),
            ],
            'plan_code' => [
                'sometimes',
                'string',
                Rule::in(PlanCode::registerableValues()),
                Rule::exists('plans', 'code')->where(fn ($query) => $query->where('is_archived', false)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Le moyen de paiement est obligatoire.',
            'method.enum' => 'Le moyen de paiement sélectionné est invalide.',
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
