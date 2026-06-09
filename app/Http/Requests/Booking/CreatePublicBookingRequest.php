<?php

namespace App\Http\Requests\Booking;

use App\Support\IvoryCoastPhone;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreatePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! IvoryCoastPhone::isValid((string) $value)) {
                    $fail('Le format du numéro de téléphone est invalide.');
                }
            }],
            'client_name' => ['required', 'string', 'min:2', 'max:100'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'date' => ['required_without:scheduled_at', 'date_format:Y-m-d'],
            'time' => ['required_without:scheduled_at', 'date_format:H:i'],
            'scheduled_at' => ['required_without_all:date,time', 'date', 'after:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('client_phone') && is_string($this->input('client_phone'))) {
            $this->merge([
                'client_phone' => IvoryCoastPhone::normalize($this->input('client_phone')),
            ]);
        }

        if ($this->has('client_name') && is_string($this->input('client_name'))) {
            $this->merge(['client_name' => trim($this->input('client_name'))]);
        }

        if ($this->filled('date') && $this->filled('time')) {
            $this->merge([
                'scheduled_at' => Carbon::parse(
                    $this->string('date')->toString().' '.$this->string('time')->toString(),
                )->toIso8601String(),
            ]);
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
