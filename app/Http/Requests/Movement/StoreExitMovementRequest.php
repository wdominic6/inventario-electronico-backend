<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExitMovementRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::in(['sale', 'damaged', 'internal_use'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.location_id' => ['required', 'integer', 'exists:locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Debe seleccionar la sucursal desde la cual se despacha la mercancía.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'reason.required' => 'El motivo de la salida es obligatorio.',
            'reason.in' => 'El motivo de salida debe ser: sale (venta), damaged (dañado/baja) o internal_use (uso interno).',
            'items.required' => 'Debe incluir al menos un componente electrónico en la salida.',
            'items.min' => 'La salida debe contener al menos un ítem.',
            'items.*.product_id.required' => 'El identificador del producto es obligatorio en cada línea.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados no existe en el catálogo.',
            'items.*.location_id.required' => 'Debe especificar la ubicación física de origen para cada ítem.',
            'items.*.location_id.exists' => 'Una de las ubicaciones físicas seleccionadas no existe.',
            'items.*.quantity.required' => 'La cantidad a retirar es obligatoria.',
            'items.*.quantity.min' => 'La cantidad a retirar debe ser mayor o igual a 1.',
        ];
    }
}
