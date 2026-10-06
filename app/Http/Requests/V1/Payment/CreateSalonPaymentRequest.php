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
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'method' => ['required', 'string', new Enum(PaymentMethod::class)],
            'mobile_money_ref' => ['nullable', 'string', 'max:255', 'required_if:method,mobile_money'],
            // Pas de paiement daté dans le futur ni antidaté de plus d'un an (audit M3)
            'paid_at' => ['sometimes', 'date', 'before_or_equal:+5 minutes', 'after_or_equal:-1 year'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => 'Le rendez-vous est obligatoire.',
            'appointment_id.exists' => 'Le rendez-vous sélectionné est introuvable.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'amount.max' => 'Le montant est trop élevé.',
            'paid_at.before_or_equal' => 'La date de paiement ne peut pas être dans le futur.',
            'paid_at.after_or_equal' => 'La date de paiement est trop ancienne.',
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
