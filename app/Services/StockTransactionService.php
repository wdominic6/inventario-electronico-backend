<?php

namespace App\Services;

use App\Models\Proveedor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StockTransactionService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra una entrada de mercancía dentro de una transacción ACID con bloqueo pesimista (FOR UPDATE).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerEntry(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $branchId = (int) $data['branch_id'];
            $referenceCode = $data['reference_code'] ?? 'ENT-'.Str::upper(Str::random(10));
            $notes = $data['notes'] ?? null;

            // Ordenar ítems por product_id y location_id para prevenir deadlocks en MySQL 8.4 InnoDB
            $sortedItems = collect($data['items'])
                ->sortBy([
                    ['product_id', 'asc'],
                    ['location_id', 'asc'],
                ])
                ->values()
                ->all();

            $entryId = $this->insertHeader('entradas_inventario', 'id_entrada', $referenceCode, [
                'codigo_entrada' => $referenceCode,
                'id_sucursal' => $branchId,
                'id_proveedor' => Proveedor::query()->firstOrCreate(
                    ['razon_social' => trim((string) ($data['supplier_name'] ?? 'Proveedor General'))],
                    ['estado' => 'activo']
                )->id_proveedor,
                'id_usuario' => $userId,
                'fecha_entrada' => now(),
                'estado' => 'Completado',
                'observacion' => $notes,
            ]);

            $items = [];
            foreach ($sortedItems as $item) {
                $productId = (int) $item['product_id'];
                $locationId = (int) $item['location_id'];
                $quantity = (int) $item['quantity'];

                $stock = $this->lockOrCreateStock($branchId, $productId, $locationId);
                $previous = (int) $stock->cantidad;
                $new = $previous + $quantity;

                DB::table('stocks')
                    ->where('id_stock', $stock->id_stock)
                    ->update([
                        'cantidad' => $new,
                        'updated_at' => now(),
                    ]);

                DB::table('detalles_entrada')->insert([
                    'id_entrada' => $entryId,
                    'id_producto' => $productId,
                    'id_ubicacion' => $locationId,
                    'cantidad' => $quantity,
                    'costo_unitario' => $item['unit_cost'] ?? null,
                ]);

                $kardexId = $this->writeKardex(
                    userId: $userId,
                    branchId: $branchId,
                    productId: $productId,
                    locationId: $locationId,
                    type: 'Entrada Compra',
                    quantity: $quantity,
                    previous: $previous,
                    result: $new,
                    notes: $notes,
                    entryId: $entryId
                );

                $items[] = [
                    'kardex_id' => $kardexId,
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => $quantity,
                    'unit_cost' => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
                    'previous_stock' => $previous,
                    'new_stock' => $new,
                    'stock_anterior' => $previous,
                    'stock_resultante' => $new,
                ];
            }

            $this->auditService->log(
                action: 'inventory.entry',
                entityType: 'entradas_inventario',
                entityId: $entryId,
                description: 'Entrada de inventario '.$referenceCode,
                details: ['branch_id' => $branchId, 'reference_code' => $referenceCode, 'items' => count($items)],
                userId: $userId
            );

            return [
                'id' => $entryId,
                'type' => 'entry',
                'branch_id' => $branchId,
                'reference_code' => $referenceCode,
                'supplier_name' => $data['supplier_name'] ?? null,
                'notes' => $notes,
                'items' => $items,
            ];
        }, 3);
    }

    /**
     * Registra una salida de mercancía validando stock disponible con bloqueo pesimista (FOR UPDATE).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerExit(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $branchId = (int) $data['branch_id'];
            $referenceCode = $data['reference_code'] ?? 'SAL-'.Str::upper(Str::random(10));
            $reason = (string) $data['reason'];
            $notes = $data['notes'] ?? null;
            $observacion = $notes !== null && trim($notes) !== ''
                ? sprintf('[%s] %s', $reason, trim($notes))
                : $reason;

            // Ordenar ítems por product_id y location_id para prevenir deadlocks en MySQL 8.4 InnoDB
            $sortedItems = collect($data['items'])
                ->sortBy([
                    ['product_id', 'asc'],
                    ['location_id', 'asc'],
                ])
                ->values()
                ->all();

            $exitId = $this->insertHeader('salidas_inventario', 'id_salida', $referenceCode, [
                'codigo_salida' => $referenceCode,
                'id_sucursal' => $branchId,
                'id_usuario' => $userId,
                'cliente_referencia' => $data['customer_reference'] ?? null,
                'fecha_salida' => now(),
                'estado' => 'Completado',
                'observacion' => $observacion,
            ]);

            $kardexType = $reason === 'sale' ? 'Salida Venta' : 'Ajuste Negativo';

            $items = [];
            foreach ($sortedItems as $item) {
                $productId = (int) $item['product_id'];
                $locationId = (int) $item['location_id'];
                $quantity = (int) $item['quantity'];

                $stock = $this->lockStock($branchId, $productId, $locationId);
                $previous = (int) ($stock?->cantidad ?? 0);

                if ($previous < $quantity) {
                    throw new ConflictHttpException(sprintf(
                        'Stock insuficiente para el componente %s en la ubicación seleccionada. Disponible: %d, solicitado: %d.',
                        $this->productLabel($productId),
                        $previous,
                        $quantity
                    ));
                }

                $new = $previous - $quantity;
                DB::table('stocks')
                    ->where('id_stock', $stock->id_stock)
                    ->update([
                        'cantidad' => $new,
                        'updated_at' => now(),
                    ]);

                DB::table('detalles_salida')->insert([
                    'id_salida' => $exitId,
                    'id_producto' => $productId,
                    'id_ubicacion' => $locationId,
                    'cantidad' => $quantity,
                ]);

                $kardexId = $this->writeKardex(
                    userId: $userId,
                    branchId: $branchId,
                    productId: $productId,
                    locationId: $locationId,
                    type: $kardexType,
                    quantity: $quantity,
                    previous: $previous,
                    result: $new,
                    notes: $observacion,
                    exitId: $exitId
                );

                $items[] = [
                    'kardex_id' => $kardexId,
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'quantity' => $quantity,
                    'previous_stock' => $previous,
                    'new_stock' => $new,
                    'stock_anterior' => $previous,
                    'stock_resultante' => $new,
                ];
            }

            $this->auditService->log(
                action: 'inventory.exit',
                entityType: 'salidas_inventario',
                entityId: $exitId,
                description: 'Salida de inventario '.$referenceCode.' ('.$reason.')',
                details: ['branch_id' => $branchId, 'reason' => $reason, 'reference_code' => $referenceCode, 'items' => count($items)],
                userId: $userId
            );

            return [
                'id' => $exitId,
                'type' => 'exit',
                'branch_id' => $branchId,
                'reference_code' => $referenceCode,
                'reason' => $reason,
                'notes' => $notes,
                'items' => $items,
            ];
        }, 3);
    }

    /**
     * Registra un traslado entre sucursales dentro de una única transacción ACID con bloqueo pesimista.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerTransfer(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $sourceBranchId = (int) $data['source_branch_id'];
            $targetBranchId = (int) $data['target_branch_id'];
            $referenceCode = $data['reference_code'] ?? 'TRA-'.Str::upper(Str::random(10));
            $notes = $data['notes'] ?? null;

            // Ordenar ítems de forma determinista para prevenir deadlocks entre sucursales
            $sortedItems = collect($data['items'])
                ->sortBy([
                    ['product_id', 'asc'],
                    ['source_location_id', 'asc'],
                    ['target_location_id', 'asc'],
                ])
                ->values()
                ->all();

            $transferId = $this->insertHeader('transferencias', 'id_transferencia', $referenceCode, [
                'codigo_transferencia' => $referenceCode,
                'id_sucursal_origen' => $sourceBranchId,
                'id_sucursal_destino' => $targetBranchId,
                'id_usuario_solicitante' => $userId,
                'estado' => 'Aprobada',
                'estado_logistico' => 'Recibida Completa',
                'fecha_solicitud' => now(),
                'fecha_despacho' => now(),
                'fecha_recepcion' => now(),
            ]);

            $items = [];
            foreach ($sortedItems as $item) {
                $productId = (int) $item['product_id'];
                $sourceLocationId = (int) $item['source_location_id'];
                $targetLocationId = (int) $item['target_location_id'];
                $quantity = (int) $item['quantity'];

                // Bloquear origen y destino en orden determinista por id_sucursal para evitar deadlocks cruzados (A->B vs B->A)
                if ($sourceBranchId < $targetBranchId) {
                    $source = $this->lockStock($sourceBranchId, $productId, $sourceLocationId);
                    $target = $this->lockOrCreateStock($targetBranchId, $productId, $targetLocationId);
                } else {
                    $target = $this->lockOrCreateStock($targetBranchId, $productId, $targetLocationId);
                    $source = $this->lockStock($sourceBranchId, $productId, $sourceLocationId);
                }

                $previous = (int) ($source?->cantidad ?? 0);
                if ($previous < $quantity) {
                    throw new ConflictHttpException(sprintf(
                        'Stock insuficiente en la sucursal de origen para trasladar el componente %s. Disponible: %d, solicitado: %d.',
                        $this->productLabel($productId),
                        $previous,
                        $quantity
                    ));
                }

                $new = $previous - $quantity;
                DB::table('stocks')
                    ->where('id_stock', $source->id_stock)
                    ->update([
                        'cantidad' => $new,
                        'updated_at' => now(),
                    ]);

                $targetPrevious = (int) $target->cantidad;
                $targetNew = $targetPrevious + $quantity;
                DB::table('stocks')
                    ->where('id_stock', $target->id_stock)
                    ->update([
                        'cantidad' => $targetNew,
                        'updated_at' => now(),
                    ]);

                DB::table('detalles_transferencia')->insert([
                    'id_transferencia' => $transferId,
                    'id_producto' => $productId,
                    'id_ubicacion_origen' => $sourceLocationId,
                    'id_ubicacion_destino' => $targetLocationId,
                    'cantidad_solicitada' => $quantity,
                    'cantidad_enviada' => $quantity,
                    'cantidad_recibida' => $quantity,
                ]);

                $sourceKardexId = $this->writeKardex(
                    userId: $userId,
                    branchId: $sourceBranchId,
                    productId: $productId,
                    locationId: $sourceLocationId,
                    type: 'Traslado Saliente',
                    quantity: $quantity,
                    previous: $previous,
                    result: $new,
                    notes: $notes,
                    transferId: $transferId
                );

                $targetKardexId = $this->writeKardex(
                    userId: $userId,
                    branchId: $targetBranchId,
                    productId: $productId,
                    locationId: $targetLocationId,
                    type: 'Traslado Entrante',
                    quantity: $quantity,
                    previous: $targetPrevious,
                    result: $targetNew,
                    notes: $notes,
                    transferId: $transferId
                );

                $items[] = [
                    'kardex_id' => $sourceKardexId,
                    'source_kardex_id' => $sourceKardexId,
                    'target_kardex_id' => $targetKardexId,
                    'product_id' => $productId,
                    'source_location_id' => $sourceLocationId,
                    'target_location_id' => $targetLocationId,
                    'quantity' => $quantity,
                    'previous_stock' => $previous,
                    'new_stock' => $new,
                    'target_previous_stock' => $targetPrevious,
                    'target_new_stock' => $targetNew,
                    'stock_anterior' => $previous,
                    'stock_resultante' => $new,
                ];
            }

            $this->auditService->log(
                action: 'inventory.transfer',
                entityType: 'transferencias',
                entityId: $transferId,
                description: 'Transferencia de inventario '.$referenceCode,
                details: [
                    'source_branch_id' => $sourceBranchId,
                    'target_branch_id' => $targetBranchId,
                    'reference_code' => $referenceCode,
                    'items' => count($items),
                ],
                userId: $userId
            );

            return [
                'id' => $transferId,
                'type' => 'transfer',
                'source_branch_id' => $sourceBranchId,
                'target_branch_id' => $targetBranchId,
                'reference_code' => $referenceCode,
                'notes' => $notes,
                'items' => $items,
            ];
        }, 3);
    }

    /**
     * Inserta la cabecera de un movimiento. Si dos peticiones concurrentes usan el mismo
     * código de referencia (carrera que la validación previa no puede detectar), el índice
     * UNIQUE de la BD lo rechaza y se responde 409 Conflict en lugar de un error 500.
     *
     * @param  array<string, mixed>  $values
     */
    private function insertHeader(string $table, string $primaryKey, string $referenceCode, array $values): int
    {
        try {
            return (int) DB::table($table)->insertGetId($values, $primaryKey);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException(sprintf(
                'El código de referencia "%s" ya fue registrado por otra operación.',
                $referenceCode
            ));
        }
    }

    private function lockStock(int $branchId, int $productId, int $locationId): ?object
    {
        return DB::table('stocks')
            ->where('id_sucursal', $branchId)
            ->where('id_producto', $productId)
            ->where('id_ubicacion', $locationId)
            ->lockForUpdate()
            ->first();
    }

    private function lockOrCreateStock(int $branchId, int $productId, int $locationId): object
    {
        // Insertar primero con insertOrIgnore evita gap-lock deadlocks cuando dos transacciones
        // concurrentes intentan crear la misma fila bajo REPEATABLE READ en MySQL 8.4 InnoDB.
        DB::table('stocks')->insertOrIgnore([
            'id_producto' => $productId,
            'id_sucursal' => $branchId,
            'id_ubicacion' => $locationId,
            'cantidad' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->lockStock($branchId, $productId, $locationId)
            ?? throw new \RuntimeException('No se pudo bloquear el registro de stock.');
    }

    private function productLabel(int $productId): string
    {
        $product = DB::table('productos')
            ->where('id_producto', $productId)
            ->first(['codigo', 'nombre']);

        if (! $product) {
            return '#'.$productId;
        }

        return sprintf('"%s" (SKU: %s)', $product->nombre, $product->codigo);
    }

    private function writeKardex(
        int $userId,
        int $branchId,
        int $productId,
        int $locationId,
        string $type,
        int $quantity,
        int $previous,
        int $result,
        ?string $notes = null,
        ?int $entryId = null,
        ?int $exitId = null,
        ?int $adjustmentId = null,
        ?int $transferId = null
    ): int {
        $movementType = DB::table('tipos_movimiento')->where('nombre_tipo', $type)->value('id_tipo_movimiento');
        if ($movementType === null) {
            throw new \RuntimeException('No existe el tipo de movimiento configurado: '.$type);
        }

        return (int) DB::table('movimientos_kardex')->insertGetId([
            'id_producto' => $productId,
            'id_sucursal' => $branchId,
            'id_ubicacion' => $locationId,
            'id_usuario' => $userId,
            'id_tipo_movimiento' => $movementType,
            'id_entrada' => $entryId,
            'id_salida' => $exitId,
            'id_ajuste' => $adjustmentId,
            'id_transferencia' => $transferId,
            'cantidad' => $quantity,
            'stock_anterior' => $previous,
            'stock_resultante' => $result,
            'observacion' => $notes,
            'fecha_hora' => now(),
        ], 'id_movimiento_kardex');
    }
}
