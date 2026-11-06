<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120', 'unique:categories,name'],
            'code_prefix' => ['required', 'string', 'max:20', 'unique:categories,code_prefix'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.unique' => 'Ya existe una categoría con este nombre.',
            'code_prefix.required' => 'El prefijo de código para la categoría es obligatorio.',
            'code_prefix.unique' => 'Este prefijo de código ya está en uso por otra categoría.',
        ];
    }
}
