<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => $this->brand,
            'model' => $this->model,
            'unit' => $this->unit,
            'min_stock' => (int) $this->min_stock,
            'unit_cost' => (float) $this->unit_cost,
            'unit_price' => (float) $this->unit_price,
            'specs' => $this->specs ?? [
                'voltage' => null,
                'current' => null,
                'resistance' => null,
                'tolerance' => null,
                'package_type' => null,
                'pins' => null,
                'extra' => null,
            ],
            'is_active' => (bool) ($this->is_active ?? true),
            'total_stock' => $this->when(
                isset($this->stocks_sum_quantity) || $this->relationLoaded('stocks'),
                fn () => isset($this->stocks_sum_quantity)
                    ? (int) $this->stocks_sum_quantity
                    : (int) $this->stocks->sum('quantity')
            ),
            'stocks_by_branch' => $this->whenLoaded('stocks', function () {
                return $this->stocks
                    ->groupBy('branch_id')
                    ->map(function ($branchStocks) {
                        $first = $branchStocks->first();

                        return [
                            'branch_id' => $first->branch_id,
                            'branch_name' => $first->relationLoaded('branch') ? $first->branch?->name : null,
                            'total_quantity' => (int) $branchStocks->sum('quantity'),
                            'locations' => $branchStocks->map(fn ($stock) => [
                                'location_id' => $stock->location_id,
                                'code' => $stock->relationLoaded('location') ? $stock->location?->code : null,
                                'shelf' => $stock->relationLoaded('location') ? $stock->location?->shelf : null,
                                'level' => $stock->relationLoaded('location') ? $stock->location?->level : null,
                                'bin' => $stock->relationLoaded('location') ? $stock->location?->bin : null,
                                'quantity' => (int) $stock->quantity,
                            ])->values(),
                        ];
                    })
                    ->values();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
