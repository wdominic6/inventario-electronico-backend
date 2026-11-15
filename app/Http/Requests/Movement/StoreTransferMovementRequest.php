<?php

namespace App\Http\Requests\Movement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
            'reference_code' => ['nullable', 'string', 'max:100'],
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

            $sourceLocationSet = array_flip(
                DB::table('ubicaciones')
                    ->where('id_sucursal', $sourceBranchId)
                    ->pluck('id_ubicacion')
                    ->map(static fn ($id): int => (int) $id)
                    ->all()
            );

            $targetLocationSet = array_flip(
                DB::table('ubicaciones')
                    ->where('id_sucursal', $targetBranchId)
                    ->pluck('id_ubicacion')
                    ->map(static fn ($id): int => (int) $id)
                    ->all()
            );

            $seenTriplets = [];
            foreach ($items as $index => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $sourceLocId = (int) ($item['source_location_id'] ?? 0);
                $targetLocId = (int) ($item['target_location_id'] ?? 0);

                if (! isset($sourceLocationSet[$sourceLocId])) {
                    $validator->errors()->add(
                        "items.{$index}.source_location_id",
                        'La ubicación física de origen no pertenece a la sucursal de origen seleccionada.'
                    );
                }

                if (! isset($targetLocationSet[$targetLocId])) {
                    $validator->errors()->add(
                        "items.{$index}.target_location_id",
                        'La ubicación física de destino no pertenece a la sucursal de destino seleccionada.'
                    );
                }

                $tripletKey = "{$productId}:{$sourceLocId}:{$targetLocId}";
                if (isset($seenTriplets[$tripletKey])) {
                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        'No puede duplicar la misma combinación de producto, ubicación de origen y destino en un mismo traslado.'
                    );
                }
                $seenTriplets[$tripletKey] = true;
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
