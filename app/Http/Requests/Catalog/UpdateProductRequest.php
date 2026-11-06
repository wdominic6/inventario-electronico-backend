<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->ignore($productId),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'min_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'specs' => ['nullable', 'array'],
            'specs.voltage' => ['nullable', 'string', 'max:50'],
            'specs.current' => ['nullable', 'string', 'max:50'],
            'specs.resistance' => ['nullable', 'string', 'max:50'],
            'specs.tolerance' => ['nullable', 'string', 'max:50'],
            'specs.package_type' => ['nullable', 'string', 'max:50'],
            'specs.pins' => ['nullable', 'integer', 'min:0'],
            'specs.extra' => ['nullable'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'El código SKU no puede estar vacío.',
            'sku.unique' => 'Este código SKU ya pertenece a otro componente.',
            'barcode.unique' => 'Este código de barras ya pertenece a otro componente.',
            'name.required' => 'El nombre del componente no puede estar vacío.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'min_stock.min' => 'El stock mínimo no puede ser negativo.',
            'unit_cost.min' => 'El costo unitario no puede ser negativo.',
            'unit_price.min' => 'El precio unitario no puede ser negativo.',
            'specs.array' => 'Las especificaciones técnicas deben enviarse como un objeto JSON.',
        ];
    }
}
