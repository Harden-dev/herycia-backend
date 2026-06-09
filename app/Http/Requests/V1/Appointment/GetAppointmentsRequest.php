<?php

namespace App\Http\Requests\V1\Appointment;

use Illuminate\Foundation\Http\FormRequest;

class GetAppointmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date_format:Y-m-d',
            'from' => 'sometimes|date_format:Y-m-d|required_with:to',
            'to' => 'sometimes|date_format:Y-m-d|after_or_equal:from|required_with:from',
            'user_id' => 'sometimes|uuid|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
            'from.date_format' => 'La date de début doit être au format AAAA-MM-JJ.',
            'to.date_format' => 'La date de fin doit être au format AAAA-MM-JJ.',
            'to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'user_id.uuid' => 'L\'identifiant du coiffeur est invalide.',
            'user_id.exists' => 'Le coiffeur sélectionné est introuvable.',
        ];
    }
}
