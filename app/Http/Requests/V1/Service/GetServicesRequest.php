<?php

namespace App\Http\Requests\V1\Service;

use Illuminate\Foundation\Http\FormRequest;

class GetServicesRequest extends FormRequest
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
            'search' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
