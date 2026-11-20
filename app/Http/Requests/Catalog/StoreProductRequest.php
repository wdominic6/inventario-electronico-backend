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
            'sku' => ['required', 'string', 'max:50', 'unique:productos,codigo'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:productos,codigo_barras'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categorias,id_categoria'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:50'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'specs' => ['present', 'array'],
            'specs.package_type' => ['nullable', 'string', 'max:50'],
            'specs.voltage' => ['nullable', 'string', 'max:50'],
            'specs.tolerance' => ['nullable', 'string', 'max:50'],
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
            'unit.required' => 'La unidad de medida es obligatoria.',
            'min_stock.required' => 'El stock mínimo de seguridad es obligatorio.',
            'min_stock.min' => 'El stock mínimo no puede ser negativo.',
            'unit_cost.required' => 'El costo unitario es obligatorio.',
            'unit_cost.min' => 'El costo unitario no puede ser negativo.',
            'unit_price.required' => 'El precio unitario de referencia es obligatorio.',
            'unit_price.min' => 'El precio unitario no puede ser negativo.',
            'specs.present' => 'Las especificaciones técnicas deben incluirse (puede ser un objeto vacío {}).',
            'specs.array' => 'Las especificaciones técnicas deben enviarse como un objeto JSON.',
            'specs.package_type.max' => 'El encapsulado (package_type) no puede superar los :max caracteres.',
            'specs.voltage.max' => 'El voltaje de operación (voltage) no puede superar los :max caracteres.',
            'specs.tolerance.max' => 'La tolerancia (tolerance) no puede superar los :max caracteres.',
        ];
    }
}
