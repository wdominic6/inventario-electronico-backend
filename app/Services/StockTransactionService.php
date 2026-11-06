<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class StockTransactionService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Registra una entrada de mercancía (recepción de proveedor o ajuste positivo)
     * dentro de una transacción ACID con bloqueo pesimista por fila (FOR UPDATE).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerEntry(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId): InventoryMovement {
            $branchId = (int) $data['branch_id'];

            // Ordenar ítems por product_id y location_id para prevenir deadlocks en MySQL 8.4 InnoDB
            $items = collect($data['items'])
                ->sortBy([
                    ['product_id', 'asc'],
                    ['location_id', 'asc'],
                ])
                ->values();

            $movement = InventoryMovement::query()->create([
                'type' => 'entry',
                'branch_id' => $branchId,
                'source_branch_id' => null,
                'target_branch_id' => $branchId,
                'user_id' => $userId,
                'reference_code' => $data['reference_code'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'reason' => 'purchase_entry',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $locationId = (int) $item['location_id'];
                $quantity = (int) $item['quantity'];
                $unitCost = isset($item['unit_cost']) ? (float) $item['unit_cost'] : null;

                $stock = $this->lockOrCreateStockRow($branchId, $productId, $locationId);

                $previousStock = (int) $stock->quantity;
                $newStock = $previousStock + $quantity;

                $stock->update([
                    'quantity' => $newStock,
                ]);

                $movement->items()->create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'target_location_id' => null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                ]);
            }

            $this->auditService->log(
                action: 'movement.entry',
                entityType: 'InventoryMovement',
                entityId: $movement->id,
                description: 'Registro de entrada de mercancía en sucursal #'.$branchId,
                details: [
                    'branch_id' => $branchId,
                    'reference_code' => $movement->reference_code,
                    'items_count' => $items->count(),
                ],
                userId: $userId
            );

            return $movement->load([
                'user',
                'branch',
                'items.product',
                'items.location',
            ]);
        });
    }

    /**
     * Registra una salida de mercancía (venta, baja por daño o uso interno)
     * validando existencias con bloqueo pesimista (SELECT ... FOR UPDATE).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerExit(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId): InventoryMovement {
            $branchId = (int) $data['branch_id'];

            // Ordenar ítems por product_id y location_id para prevenir deadlocks
            $items = collect($data['items'])
                ->sortBy([
                    ['product_id', 'asc'],
                    ['location_id', 'asc'],
                ])
                ->values();

            $movement = InventoryMovement::query()->create([
                'type' => 'exit',
                'branch_id' => $branchId,
                'source_branch_id' => $branchId,
                'target_branch_id' => null,
                'user_id' => $userId,
                'reference_code' => $data['reference_code'] ?? null,
                'supplier_name' => null,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $locationId = (int) $item['location_id'];
                $requestedQuantity = (int) $item['quantity'];

                $stock = ProductStock::query()
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->where('location_id', $locationId)
                    ->lockForUpdate()
                    ->first();

                $availableQuantity = $stock ? (int) $stock->quantity : 0;

                if (! $stock || $availableQuantity < $requestedQuantity) {
                    $productLabel = $this->resolveProductLabel($productId);

                    throw new HttpResponseException(response()->json([
                        'message' => sprintf(
                            'Stock insuficiente para el componente %s en la ubicación seleccionada. Disponible: %d, solicitado: %d.',
                            $productLabel,
                            $availableQuantity,
                            $requestedQuantity
                        ),
                    ], 409));
                }

                $previousStock = $availableQuantity;
                $newStock = $previousStock - $requestedQuantity;

                $stock->update([
                    'quantity' => $newStock,
                ]);

                $movement->items()->create([
                    'product_id' => $productId,
                    'location_id' => $locationId,
                    'target_location_id' => null,
                    'quantity' => $requestedQuantity,
                    'unit_cost' => null,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                ]);
            }

            $this->auditService->log(
                action: 'movement.exit',
                entityType: 'InventoryMovement',
                entityId: $movement->id,
                description: 'Registro de salida de mercancía ('.$data['reason'].') en sucursal #'.$branchId,
                details: [
                    'branch_id' => $branchId,
                    'reason' => $data['reason'],
                    'reference_code' => $movement->reference_code,
                    'items_count' => $items->count(),
                ],
                userId: $userId
            );

            return $movement->load([
                'user',
                'branch',
                'items.product',
                'items.location',
            ]);
        });
    }

    /**
     * Registra un traslado entre sucursales (descuenta en origen e incrementa en destino
     * dentro de una única transacción ACID con bloqueo pesimista).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerTransfer(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId): InventoryMovement {
            $sourceBranchId = (int) $data['source_branch_id'];
            $targetBranchId = (int) $data['target_branch_id'];

            // Ordenar ítems de forma determinista para prevenir deadlocks entre sucursales
            $items = collect($data['items'])
                ->sortBy([
                    ['product_id', 'asc'],
                    ['source_location_id', 'asc'],
                    ['target_location_id', 'asc'],
                ])
                ->values();

            $movement = InventoryMovement::query()->create([
                'type' => 'transfer',
                'branch_id' => $sourceBranchId,
                'source_branch_id' => $sourceBranchId,
                'target_branch_id' => $targetBranchId,
                'user_id' => $userId,
                'reference_code' => $data['reference_code'] ?? null,
                'supplier_name' => null,
                'reason' => 'branch_transfer',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $sourceLocationId = (int) $item['source_location_id'];
                $targetLocationId = (int) $item['target_location_id'];
                $requestedQuantity = (int) $item['quantity'];

                $sourceStock = ProductStock::query()
                    ->where('branch_id', $sourceBranchId)
                    ->where('product_id', $productId)
                    ->where('location_id', $sourceLocationId)
                    ->lockForUpdate()
                    ->first();

                $availableQuantity = $sourceStock ? (int) $sourceStock->quantity : 0;

                if (! $sourceStock || $availableQuantity < $requestedQuantity) {
                    $productLabel = $this->resolveProductLabel($productId);

                    throw new HttpResponseException(response()->json([
                        'message' => sprintf(
                            'Stock insuficiente en la sucursal de origen para trasladar el componente %s. Disponible: %d, solicitado: %d.',
                            $productLabel,
                            $availableQuantity,
                            $requestedQuantity
                        ),
                    ], 409));
                }

                $sourcePreviousStock = $availableQuantity;
                $sourceNewStock = $sourcePreviousStock - $requestedQuantity;

                $sourceStock->update([
                    'quantity' => $sourceNewStock,
                ]);

                $targetStock = $this->lockOrCreateStockRow($targetBranchId, $productId, $targetLocationId);
                $targetPreviousStock = (int) $targetStock->quantity;
                $targetNewStock = $targetPreviousStock + $requestedQuantity;

                $targetStock->update([
                    'quantity' => $targetNewStock,
                ]);

                $movement->items()->create([
                    'product_id' => $productId,
                    'location_id' => $sourceLocationId,
                    'target_location_id' => $targetLocationId,
                    'quantity' => $requestedQuantity,
                    'unit_cost' => null,
                    'previous_stock' => $sourcePreviousStock,
                    'new_stock' => $sourceNewStock,
                ]);
            }

            $this->auditService->log(
                action: 'movement.transfer',
                entityType: 'InventoryMovement',
                entityId: $movement->id,
                description: sprintf('Traslado de mercancía desde sucursal #%d hacia sucursal #%d', $sourceBranchId, $targetBranchId),
                details: [
                    'source_branch_id' => $sourceBranchId,
                    'target_branch_id' => $targetBranchId,
                    'reference_code' => $movement->reference_code,
                    'items_count' => $items->count(),
                ],
                userId: $userId
            );

            return $movement->load([
                'user',
                'branch',
                'sourceBranch',
                'targetBranch',
                'items.product',
                'items.location',
                'items.targetLocation',
            ]);
        });
    }

    /**
     * Obtiene y bloquea con FOR UPDATE la fila de stock para (branch, product, location).
     * Si no existe aún el registro en product_stocks, lo inicializa en 0 y lo bloquea.
     */
    protected function lockOrCreateStockRow(int $branchId, int $productId, int $locationId): ProductStock
    {
        $stock = ProductStock::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        ProductStock::query()->firstOrCreate(
            [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'location_id' => $locationId,
            ],
            [
                'quantity' => 0,
            ]
        );

        return ProductStock::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Obtiene una etiqueta legible del componente electrónico para mensajes de conflicto 409.
     */
    protected function resolveProductLabel(int $productId): string
    {
        $product = Product::query()->find($productId);

        if (! $product) {
            return '#'.$productId;
        }

        return sprintf('"%s" (SKU: %s)', $product->name, $product->sku);
    }
}
