<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            // La columna codigo_entrada es VARCHAR(50) UNIQUE.
            'reference_code' => ['nullable', 'string', 'max:50', Rule::unique('entradas_inventario', 'codigo_entrada')],
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

            $branchActive = DB::table('sucursales')
                ->where('id_sucursal', $branchId)
                ->where('estado', 'activa')
                ->exists();

            if (! $branchActive) {
                $validator->errors()->add(
                    'branch_id',
                    'La sucursal de ingreso no se encuentra activa.'
                );
            }

            $validLocationIds = DB::table('ubicaciones')
                ->where('id_sucursal', $branchId)
                ->where('estado', 'disponible')
                ->pluck('id_ubicacion')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $validSet = array_flip($validLocationIds);

            $productIds = array_values(array_unique(array_map(
                static fn (array $item): int => (int) ($item['product_id'] ?? 0),
                $items
            )));
            $activeProductSet = array_flip(
                DB::table('productos')
                    ->whereIn('id_producto', $productIds)
                    ->where('estado', 'activo')
                    ->pluck('id_producto')
                    ->map(static fn ($id): int => (int) $id)
                    ->all()
            );

            $seenPairs = [];
            foreach ($items as $index => $item) {
                $locationId = (int) ($item['location_id'] ?? 0);
                $productId = (int) ($item['product_id'] ?? 0);

                if (! isset($activeProductSet[$productId])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'El componente electrónico seleccionado no se encuentra activo.'
                    );
                }

                if (! isset($validSet[$locationId])) {
                    $validator->errors()->add(
                        "items.{$index}.location_id",
                        'La ubicación física seleccionada no pertenece a la sucursal de ingreso indicada o no está disponible.'
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
            'reference_code.max' => 'El código de referencia no puede superar los :max caracteres.',
            'reference_code.unique' => 'Ya existe una entrada registrada con este código de referencia.',
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
