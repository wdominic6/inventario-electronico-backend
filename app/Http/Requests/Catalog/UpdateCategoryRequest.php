<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $categoryId = $this->route('category')?->id ?? $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'code_prefix' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('categories', 'code_prefix')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría no puede estar vacío.',
            'name.unique' => 'Ya existe otra categoría con este nombre.',
            'code_prefix.required' => 'El prefijo de código no puede estar vacío.',
            'code_prefix.unique' => 'Ya existe otra categoría con este prefijo de código.',
        ];
    }
}
