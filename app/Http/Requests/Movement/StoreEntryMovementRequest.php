<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

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
            'branch_id' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            'supplier_name' => ['required', 'string', 'max:150'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:productos,id_producto'],
            'items.*.location_id' => ['required', 'integer', 'exists:ubicaciones,id_ubicacion'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $branchId = (int) $this->input('branch_id');
            $items = (array) $this->input('items', []);

            $validLocationIds = DB::table('ubicaciones')
                ->where('id_sucursal', $branchId)
                ->pluck('id_ubicacion')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $validSet = array_flip($validLocationIds);

            $seenPairs = [];
            foreach ($items as $index => $item) {
                $locationId = (int) ($item['location_id'] ?? 0);
                $productId = (int) ($item['product_id'] ?? 0);

                if (! isset($validSet[$locationId])) {
                    $validator->errors()->add(
                        "items.{$index}.location_id",
                        'La ubicación física seleccionada no pertenece a la sucursal de ingreso indicada.'
                    );
                }

                $pairKey = "{$productId}:{$locationId}";
                if (isset($seenPairs[$pairKey])) {
                    $validator->errors()->add(
                        "items.{$index}.location_id",
                        'No puede duplicar el mismo componente y ubicación física en varias líneas de una misma entrada.'
                    );
                }
                $seenPairs[$pairKey] = true;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Debe seleccionar la sucursal de ingreso.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'supplier_name.required' => 'El proveedor es obligatorio.',
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
