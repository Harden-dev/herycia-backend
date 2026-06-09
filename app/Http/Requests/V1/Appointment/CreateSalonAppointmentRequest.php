<?php

namespace App\Http\Requests\V1\Appointment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreateSalonAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'Le client est obligatoire.',
            'client_id.exists' => 'Le client sélectionné est introuvable.',
            'user_id.required' => 'Le coiffeur est obligatoire.',
            'user_id.exists' => 'Le coiffeur sélectionné est introuvable.',
            'service_id.required' => 'Le service est obligatoire.',
            'service_id.exists' => 'Le service sélectionné est introuvable.',
            'scheduled_at.required' => 'La date du rendez-vous est obligatoire.',
            'scheduled_at.date' => 'La date du rendez-vous est invalide.',
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
