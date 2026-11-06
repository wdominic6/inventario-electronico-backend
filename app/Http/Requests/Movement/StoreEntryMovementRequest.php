<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryMovementRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.location_id' => ['required', 'integer', 'exists:locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Debe seleccionar la sucursal de ingreso.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'items.required' => 'Debe incluir al menos un componente electrónico en la entrada.',
            'items.min' => 'La entrada debe contener al menos un ítem.',
            'items.*.product_id.required' => 'El identificador del producto es obligatorio en cada línea.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados no existe en el catálogo.',
            'items.*.location_id.required' => 'Debe indicar la ubicación física (estante/casillero) para cada producto.',
            'items.*.location_id.exists' => 'Una de las ubicaciones físicas seleccionadas no existe.',
            'items.*.quantity.required' => 'La cantidad a ingresar es obligatoria.',
            'items.*.quantity.min' => 'La cantidad a ingresar debe ser mayor o igual a 1.',
            'items.*.unit_cost.min' => 'El costo unitario no puede ser negativo.',
        ];
    }
}
