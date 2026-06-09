<?php

namespace App\Http\Requests\V1\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class GetDashboardActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => 'sometimes|integer|min:1|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'limit.integer' => 'La limite doit être un entier.',
            'limit.min' => 'La limite doit être au minimum 1.',
            'limit.max' => 'La limite ne peut pas dépasser 50.',
        ];
    }
}
