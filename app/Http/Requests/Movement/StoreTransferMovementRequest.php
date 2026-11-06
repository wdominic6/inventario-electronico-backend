<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferMovementRequest extends FormRequest
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
            'source_branch_id' => ['required', 'integer', 'exists:branches,id', 'different:target_branch_id'],
            'target_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.source_location_id' => ['required', 'integer', 'exists:locations,id'],
            'items.*.target_location_id' => ['required', 'integer', 'exists:locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_branch_id.required' => 'Debe seleccionar la sucursal de origen del traslado.',
            'source_branch_id.exists' => 'La sucursal de origen seleccionada no existe.',
            'source_branch_id.different' => 'La sucursal de origen y la sucursal de destino deben ser diferentes.',
            'target_branch_id.required' => 'Debe seleccionar la sucursal de destino del traslado.',
            'target_branch_id.exists' => 'La sucursal de destino seleccionada no existe.',
            'items.required' => 'Debe incluir al menos un componente electrónico para trasladar.',
            'items.min' => 'El traslado debe contener al menos un ítem.',
            'items.*.product_id.required' => 'El identificador del producto es obligatorio en cada línea.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados no existe en el catálogo.',
            'items.*.source_location_id.required' => 'Debe indicar la ubicación física de origen para cada ítem.',
            'items.*.source_location_id.exists' => 'Una de las ubicaciones físicas de origen no existe.',
            'items.*.target_location_id.required' => 'Debe indicar la ubicación física de destino para cada ítem.',
            'items.*.target_location_id.exists' => 'Una de las ubicaciones físicas de destino no existe.',
            'items.*.quantity.required' => 'La cantidad a trasladar es obligatoria.',
            'items.*.quantity.min' => 'La cantidad a trasladar debe ser mayor o igual a 1.',
        ];
    }
}
