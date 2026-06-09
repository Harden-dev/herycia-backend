<?php

namespace App\Http\Requests\V1\Payment;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class CreateSalonPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'uuid', 'exists:appointments,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', new Enum(PaymentMethod::class)],
            'mobile_money_ref' => ['nullable', 'string', 'max:255', 'required_if:method,mobile_money'],
            'paid_at' => ['sometimes', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => 'Le rendez-vous est obligatoire.',
            'appointment_id.exists' => 'Le rendez-vous sélectionné est introuvable.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'method.required' => 'La méthode de paiement est obligatoire.',
            'mobile_money_ref.required_if' => 'La référence Mobile Money est obligatoire pour ce mode de paiement.',
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
