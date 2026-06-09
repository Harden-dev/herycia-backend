<?php

namespace App\Http\Requests\V1\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GetPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'date' => 'sometimes|date_format:Y-m-d',
            'from' => 'sometimes|date_format:Y-m-d|required_with:to',
            'to' => 'sometimes|date_format:Y-m-d|after_or_equal:from|required_with:from',
            'method' => ['sometimes', 'string', new Enum(PaymentMethod::class)],
            'status' => ['sometimes', 'string', new Enum(PaymentStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
            'from.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'to.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
