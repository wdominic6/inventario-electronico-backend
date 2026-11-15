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
        $categoryId = $this->route('category')?->id_categoria ?? $this->route('category');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categorias', 'nombre_categoria')->ignore($categoryId, 'id_categoria'),
            ],
            'code_prefix' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('categorias', 'prefijo_codigo')->ignore($categoryId, 'id_categoria')],
            'description' => ['nullable', 'string'],
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
        ];
    }
}
