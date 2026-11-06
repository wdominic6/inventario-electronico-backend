<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'branch_id' => $this->branch_id,
            'source_branch_id' => $this->source_branch_id,
            'target_branch_id' => $this->target_branch_id,
            'user_id' => $this->user_id,
            'reference_code' => $this->reference_code,
            'supplier_name' => $this->supplier_name,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'source_branch' => new BranchResource($this->whenLoaded('sourceBranch')),
            'target_branch' => new BranchResource($this->whenLoaded('targetBranch')),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->relationLoaded('product') && $item->product
                        ? [
                            'id' => $item->product->id,
                            'sku' => $item->product->sku,
                            'barcode' => $item->product->barcode,
                            'name' => $item->product->name,
                            'unit' => $item->product->unit,
                        ]
                        : null,
                    'location_id' => $item->location_id,
                    'location' => $item->relationLoaded('location') && $item->location
                        ? new LocationResource($item->location)
                        : null,
                    'target_location_id' => $item->target_location_id,
                    'target_location' => $item->relationLoaded('targetLocation') && $item->targetLocation
                        ? new LocationResource($item->targetLocation)
                        : null,
                    'quantity' => (int) $item->quantity,
                    'unit_cost' => $item->unit_cost !== null ? (float) $item->unit_cost : null,
                    'previous_stock' => (int) $item->previous_stock,
                    'new_stock' => (int) $item->new_stock,
                ]);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
