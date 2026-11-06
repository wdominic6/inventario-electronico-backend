<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'sku' => ['required', 'string', 'max:60', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:30'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
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
            'sku.required' => 'El código SKU del componente electrónico es obligatorio.',
            'sku.unique' => 'Ya existe un componente registrado con este código SKU.',
            'barcode.unique' => 'Ya existe un componente registrado con este código de barras.',
            'name.required' => 'El nombre del componente electrónico es obligatorio.',
            'category_id.required' => 'La categoría del componente es obligatoria.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'unit.required' => 'La unidad de medida es obligatoria (ej. UND, PAQ, ROLLO).',
            'min_stock.required' => 'El stock mínimo de seguridad es obligatorio.',
            'min_stock.min' => 'El stock mínimo no puede ser negativo.',
            'unit_cost.required' => 'El costo unitario es obligatorio.',
            'unit_cost.min' => 'El costo unitario no puede ser negativo.',
            'unit_price.required' => 'El precio unitario de referencia es obligatorio.',
            'unit_price.min' => 'El precio unitario no puede ser negativo.',
            'specs.array' => 'Las especificaciones técnicas deben enviarse como un objeto JSON.',
        ];
    }
}
