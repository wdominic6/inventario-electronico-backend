<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Movement\StoreEntryMovementRequest;
use App\Http\Requests\Movement\StoreExitMovementRequest;
use App\Http\Requests\Movement\StoreTransferMovementRequest;
use App\Http\Resources\MovementResource;
use App\Models\InventoryMovement;
use App\Services\StockTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MovementController extends Controller
{
    public function __construct(
        protected StockTransactionService $stockTransactionService
    ) {}

    /**
     * GET /api/movements
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InventoryMovement::query()->with([
            'user',
            'branch',
            'sourceBranch',
            'targetBranch',
            'items.product',
            'items.location',
            'items.targetLocation',
        ]);

        if ($request->filled('branch_id')) {
            $branchId = $request->integer('branch_id');
            $query->where(function ($subQuery) use ($branchId): void {
                $subQuery->where('branch_id', $branchId)
                    ->orWhere('source_branch_id', $branchId)
                    ->orWhere('target_branch_id', $branchId);
            });
        }

        if ($request->filled('product_id')) {
            $productId = $request->integer('product_id');
            $query->whereHas('items', fn ($itemQuery) => $itemQuery->where('product_id', $productId));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return MovementResource::collection(
            $query->latest('id')->paginate($perPage)
        );
    }

    /**
     * GET /api/movements/{movement}
     */
    public function show(InventoryMovement $movement): MovementResource
    {
        $movement->load([
            'user',
            'branch',
            'sourceBranch',
            'targetBranch',
            'items.product',
            'items.location',
            'items.targetLocation',
        ]);

        return new MovementResource($movement);
    }

    /**
     * POST /api/movements/entry
     */
    public function storeEntry(StoreEntryMovementRequest $request): JsonResponse
    {
        $movement = $this->stockTransactionService->registerEntry(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new MovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/movements/exit
     */
    public function storeExit(StoreExitMovementRequest $request): JsonResponse
    {
        $movement = $this->stockTransactionService->registerExit(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new MovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/movements/transfer
     */
    public function storeTransfer(StoreTransferMovementRequest $request): JsonResponse
    {
        $movement = $this->stockTransactionService->registerTransfer(
            $request->validated(),
            (int) $request->user()->id
        );

        return (new MovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }
}
