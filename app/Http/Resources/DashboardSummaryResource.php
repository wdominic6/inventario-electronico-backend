<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'active_skus' => (int) $this->resource['active_skus'],
            'inventory_value' => (float) $this->resource['inventory_value'],
            'low_stock_alerts' => (int) $this->resource['low_stock_alerts'],
            'movements_today' => (int) $this->resource['movements_today'],
            'movements_this_week' => (int) $this->resource['movements_this_week'],
            'operational_branches' => (int) $this->resource['operational_branches'],
        ];
    }
}
