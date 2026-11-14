<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\Models\Proveedor;

class StockTransactionService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerEntry(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $entryId = DB::table('entradas_inventario')->insertGetId([
                'codigo_entrada' => $data['reference_code'] ?? 'ENT-'.Str::upper(Str::random(10)),
                'id_sucursal' => $data['branch_id'],
                'id_proveedor' => Proveedor::query()->firstOrCreate(
                    ['razon_social' => trim($data['supplier_name'])],
                    ['estado' => 'activo']
                )->id_proveedor,
                'id_usuario' => $userId,
                'estado' => 'Completado',
                'observacion' => $data['notes'] ?? null,
            ], 'id_entrada');

            $items = [];
            foreach ($data['items'] as $item) {
                $stock = $this->lockStock($data['branch_id'], $item['product_id'], $item['location_id']);
                $previous = (int) ($stock?->cantidad ?? 0);
                $new = $previous + (int) $item['quantity'];
                $this->saveStock($data['branch_id'], $item['product_id'], $item['location_id'], $new);

                DB::table('detalles_entrada')->insert([
                    'id_entrada' => $entryId,
                    'id_producto' => $item['product_id'],
                    'id_ubicacion' => $item['location_id'],
                    'cantidad' => $item['quantity'],
                    'costo_unitario' => $item['unit_cost'] ?? null,
                ]);
                $this->writeKardex($userId, $data['branch_id'], $item, 'Entrada Compra', $item['quantity'], $previous, $new, $entryId);
                $items[] = [...$item, 'stock_anterior' => $previous, 'stock_resultante' => $new];
            }

            $this->auditService->log('inventory.entry', 'entradas_inventario', $entryId, 'Entrada de inventario', ['items' => count($items)], $userId);

            return ['id' => $entryId, 'type' => 'entry', 'items' => $items];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerExit(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $exitId = DB::table('salidas_inventario')->insertGetId([
                'codigo_salida' => $data['reference_code'] ?? 'SAL-'.Str::upper(Str::random(10)),
                'id_sucursal' => $data['branch_id'],
                'id_usuario' => $userId,
                'cliente_referencia' => $data['customer_reference'] ?? null,
                'estado' => 'Completado',
                'observacion' => $data['notes'] ?? $data['reason'],
            ], 'id_salida');

            $items = [];
            foreach ($data['items'] as $item) {
                $stock = $this->lockStock($data['branch_id'], $item['product_id'], $item['location_id']);
                $previous = (int) ($stock?->cantidad ?? 0);
                $quantity = (int) $item['quantity'];
                if ($previous < $quantity) {
                    throw new ConflictHttpException('Stock insuficiente para el producto '.$item['product_id'].'.');
                }

                $new = $previous - $quantity;
                $this->saveStock($data['branch_id'], $item['product_id'], $item['location_id'], $new);
                DB::table('detalles_salida')->insert([
                    'id_salida' => $exitId,
                    'id_producto' => $item['product_id'],
                    'id_ubicacion' => $item['location_id'],
                    'cantidad' => $quantity,
                ]);
                $this->writeKardex($userId, $data['branch_id'], $item, 'Salida Venta', $quantity, $previous, $new, null, $exitId);
                $items[] = [...$item, 'stock_anterior' => $previous, 'stock_resultante' => $new];
            }

            $this->auditService->log('inventory.exit', 'salidas_inventario', $exitId, 'Salida de inventario', ['items' => count($items)], $userId);

            return ['id' => $exitId, 'type' => 'exit', 'items' => $items];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function registerTransfer(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $transferId = DB::table('transferencias')->insertGetId([
                'codigo_transferencia' => $data['reference_code'] ?? 'TRA-'.Str::upper(Str::random(10)),
                'id_sucursal_origen' => $data['source_branch_id'],
                'id_sucursal_destino' => $data['target_branch_id'],
                'id_usuario_solicitante' => $userId,
                'estado' => 'Aprobada',
                'estado_logistico' => 'Recibida Completa',
                'fecha_despacho' => now(),
                'fecha_recepcion' => now(),
            ], 'id_transferencia');

            $items = [];
            foreach ($data['items'] as $item) {
                $source = $this->lockStock($data['source_branch_id'], $item['product_id'], $item['source_location_id']);
                $previous = (int) ($source?->cantidad ?? 0);
                $quantity = (int) $item['quantity'];
                if ($previous < $quantity) {
                    throw new ConflictHttpException('Stock insuficiente para el producto '.$item['product_id'].'.');
                }

                $new = $previous - $quantity;
                $this->saveStock($data['source_branch_id'], $item['product_id'], $item['source_location_id'], $new);
                $target = $this->lockStock($data['target_branch_id'], $item['product_id'], $item['target_location_id']);
                $targetNew = (int) ($target?->cantidad ?? 0) + $quantity;
                $this->saveStock($data['target_branch_id'], $item['product_id'], $item['target_location_id'], $targetNew);

                DB::table('detalles_transferencia')->insert([
                    'id_transferencia' => $transferId,
                    'id_producto' => $item['product_id'],
                    'id_ubicacion_origen' => $item['source_location_id'],
                    'id_ubicacion_destino' => $item['target_location_id'],
                    'cantidad_solicitada' => $quantity,
                    'cantidad_enviada' => $quantity,
                    'cantidad_recibida' => $quantity,
                ]);
                $this->writeKardex($userId, $data['source_branch_id'], [
                    'product_id' => $item['product_id'],
                    'location_id' => $item['source_location_id'],
                ], 'Traslado Saliente', $quantity, $previous, $new, null, null, null, $transferId);
                $this->writeKardex($userId, $data['target_branch_id'], [
                    'product_id' => $item['product_id'],
                    'location_id' => $item['target_location_id'],
                ], 'Traslado Entrante', $quantity, $target?->cantidad ?? 0, $targetNew, null, null, null, $transferId);
                $items[] = [...$item, 'stock_anterior' => $previous, 'stock_resultante' => $new];
            }

            $this->auditService->log('inventory.transfer', 'transferencias', $transferId, 'Transferencia de inventario', ['items' => count($items)], $userId);

            return ['id' => $transferId, 'type' => 'transfer', 'items' => $items];
        });
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

    private function saveStock(int $branchId, int $productId, int $locationId, int $quantity): void
    {
        $stock = DB::table('stocks')
            ->where('id_sucursal', $branchId)
            ->where('id_producto', $productId)
            ->where('id_ubicacion', $locationId)
            ->first();

        if ($stock === null) {
            DB::table('stocks')->insert([
                'id_producto' => $productId,
                'id_sucursal' => $branchId,
                'id_ubicacion' => $locationId,
                'cantidad' => $quantity,
            ]);
            return;
        }

        DB::table('stocks')
            ->where('id_stock', $stock->id_stock)
            ->update(['cantidad' => $quantity]);
    }

    private function writeKardex(
        int $userId,
        int $branchId,
        array $item,
        string $type,
        int $quantity,
        int $previous,
        int $result,
        ?int $entryId = null,
        ?int $exitId = null,
        ?int $adjustmentId = null,
        ?int $transferId = null
    ): void {
        $movementType = DB::table('tipos_movimiento')->where('nombre_tipo', $type)->value('id_tipo_movimiento');
        if ($movementType === null) {
            throw new \RuntimeException('No existe el tipo de movimiento configurado: '.$type);
        }

        DB::table('movimientos_kardex')->insert([
            'id_producto' => $item['product_id'],
            'id_sucursal' => $branchId,
            'id_ubicacion' => $item['location_id'],
            'id_usuario' => $userId,
            'id_tipo_movimiento' => $movementType,
            'id_entrada' => $entryId,
            'id_salida' => $exitId,
            'id_ajuste' => $adjustmentId,
            'id_transferencia' => $transferId,
            'cantidad' => $quantity,
            'stock_anterior' => $previous,
            'stock_resultante' => $result,
        ]);
    }
}
