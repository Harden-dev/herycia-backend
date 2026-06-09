<?php

namespace App\Http\Requests\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetDashboardRevenueStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', Rule::in(['day', 'week', 'month', 'year'])],
            'date' => 'sometimes|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'period.required' => 'La période est requise.',
            'period.in' => 'La période doit être day, week, month ou year.',
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
        ];
    }
}
