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
            'name' => ['required', 'string', 'max:100', 'unique:categorias,nombre_categoria'],
            'code_prefix' => ['required', 'string', 'max:30', 'unique:categorias,prefijo_codigo'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'code_prefix.required' => 'El prefijo de código es obligatorio.',
            'name.unique' => 'Ya existe una categoría con este nombre.',
        ];
    }
}
