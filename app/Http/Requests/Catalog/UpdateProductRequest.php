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
        $productId = $this->route('product')?->id_producto ?? $this->route('product');

        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('productos', 'codigo')->ignore($productId, 'id_producto'),
            ],
            'barcode' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('productos', 'codigo_barras')->ignore($productId, 'id_producto'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categorias,id_categoria'],
            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'min_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'unit_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'specs' => ['sometimes', 'required', 'array'],
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
            'barcode.required' => 'El código de barras no puede estar vacío.',
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
