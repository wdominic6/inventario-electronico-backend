<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'source_branch_id' => ['required', 'integer', 'exists:sucursales,id_sucursal', 'different:target_branch_id'],
            'target_branch_id' => ['required', 'integer', 'exists:sucursales,id_sucursal'],
            // La columna codigo_transferencia es VARCHAR(50) UNIQUE.
            'reference_code' => ['nullable', 'string', 'max:50', Rule::unique('transferencias', 'codigo_transferencia')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:productos,id_producto'],
            'items.*.source_location_id' => ['required', 'integer', 'exists:ubicaciones,id_ubicacion'],
            'items.*.target_location_id' => ['required', 'integer', 'exists:ubicaciones,id_ubicacion'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sourceBranchId = (int) $this->input('source_branch_id');
            $targetBranchId = (int) $this->input('target_branch_id');
            $items = (array) $this->input('items', []);

            $activeBranches = array_flip(
                DB::table('sucursales')
                    ->whereIn('id_sucursal', [$sourceBranchId, $targetBranchId])
                    ->where('estado', 'activa')
                    ->pluck('id_sucursal')
                    ->map(static fn ($id): int => (int) $id)
                    ->all()
            );

            if (! isset($activeBranches[$sourceBranchId])) {
                $validator->errors()->add(
                    'source_branch_id',
                    'La sucursal de origen no se encuentra activa.'
                );
            }

            if (! isset($activeBranches[$targetBranchId])) {
                $validator->errors()->add(
                    'target_branch_id',
                    'La sucursal de destino no se encuentra activa.'
                );
            }

            $sourceLocationSet = array_flip(
                DB::table('ubicaciones')
                    ->where('id_sucursal', $sourceBranchId)
                    ->where('estado', 'disponible')
                    ->pluck('id_ubicacion')
                    ->map(static fn ($id): int => (int) $id)
                    ->all()
            );

            $targetLocationSet = array_flip(
                DB::table('ubicaciones')
                    ->where('id_sucursal', $targetBranchId)
                    ->where('estado', 'disponible')
                    ->pluck('id_ubicacion')
                    ->map(static fn ($id): int => (int) $id)
                    ->all()
            );

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

            // Restricción física uq_detalle_transfer_origen: (id_transferencia, id_producto, id_ubicacion_origen)
            $seenOriginPairs = [];
            foreach ($items as $index => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $sourceLocId = (int) ($item['source_location_id'] ?? 0);
                $targetLocId = (int) ($item['target_location_id'] ?? 0);

                if (! isset($activeProductSet[$productId])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'El componente electrónico seleccionado no se encuentra activo.'
                    );
                }

                if (! isset($sourceLocationSet[$sourceLocId])) {
                    $validator->errors()->add(
                        "items.{$index}.source_location_id",
                        'La ubicación física de origen no pertenece a la sucursal de origen seleccionada o no está disponible.'
                    );
                }

                if (! isset($targetLocationSet[$targetLocId])) {
                    $validator->errors()->add(
                        "items.{$index}.target_location_id",
                        'La ubicación física de destino no pertenece a la sucursal de destino seleccionada o no está disponible.'
                    );
                }

                $originPairKey = "{$productId}:{$sourceLocId}";
                if (isset($seenOriginPairs[$originPairKey])) {
                    $validator->errors()->add(
                        "items.{$index}.source_location_id",
                        'No puede duplicar el mismo producto y ubicación física de origen en varias líneas de un mismo traslado.'
                    );
                }
                $seenOriginPairs[$originPairKey] = true;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_branch_id.required' => 'Debe seleccionar la sucursal de origen del traslado.',
            'reference_code.max' => 'El código de referencia no puede superar los :max caracteres.',
            'reference_code.unique' => 'Ya existe un traslado registrado con este código de referencia.',
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
