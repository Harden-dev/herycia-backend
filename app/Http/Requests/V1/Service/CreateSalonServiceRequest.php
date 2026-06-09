<?php

namespace App\Http\Requests\V1\Service;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreateSalonServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la prestation est obligatoire.',
            'duration_min.required' => 'La durée est obligatoire.',
            'duration_min.min' => 'La durée doit être d\'au moins 5 minutes.',
            'duration_min.max' => 'La durée ne peut pas dépasser 480 minutes.',
            'price.required' => 'Le prix est obligatoire.',
            'price.min' => 'Le prix doit être positif ou nul.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
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
