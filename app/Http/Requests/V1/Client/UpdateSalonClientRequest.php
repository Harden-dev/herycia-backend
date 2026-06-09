<?php

namespace App\Http\Requests\V1\Client;

use App\Support\IvoryCoastPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UpdateSalonClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('id');
        $salonId = auth('api')->user()?->salon_id;

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            'phone' => [
                'sometimes',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! IvoryCoastPhone::isValid((string) $value)) {
                        $fail('Le format du numéro de téléphone est invalide.');
                    }
                },
                Rule::unique('clients', 'phone')
                    ->where('salon_id', $salonId)
                    ->ignore($clientId),
            ],
            'whatsapp_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Ce numéro de téléphone est déjà enregistré pour un client de ce salon.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name') && is_string($this->input('name'))) {
            $payload['name'] = trim($this->input('name'));
        }

        if ($this->has('phone') && is_string($this->input('phone'))) {
            $payload['phone'] = IvoryCoastPhone::normalize($this->input('phone'));
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
