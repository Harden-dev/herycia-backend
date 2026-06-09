<?php

namespace App\Http\Requests\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class GetDashboardOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ];
    }
}
