<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class SearchStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $this->merge(['q' => trim((string) $this->input('q'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'package_type' => ['nullable', 'string', 'max:50'],
            'voltage' => ['nullable', 'string', 'max:50'],
            'only_available' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.min' => 'El criterio de búsqueda debe tener como mínimo 2 caracteres.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }
}
