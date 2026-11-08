<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SearchStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    /**
     * GET /api/stock/search
     */
    public function buscarStock(SearchStockRequest $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with(['category', 'stocks.branch', 'stocks.location'])
            ->withSum('stocks', 'quantity')
            ->where('is_active', true);

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function (Builder $products) use ($search): void {
                $products
                    ->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('stocks', function (Builder $stocks) use ($request): void {
                $stocks->where('branch_id', $request->integer('branch_id'));
            });
        }

        if ($request->filled('package_type')) {
            $query->where('specs->package_type', $request->string('package_type')->toString());
        }

        if ($request->filled('voltage')) {
            $query->where('specs->voltage', $request->string('voltage')->toString());
        }

        if ($request->boolean('only_available')) {
            $query->whereHas('stocks', function (Builder $stocks): void {
                $stocks->where('quantity', '>', 0);
            });
        }

        $perPage = $request->integer('per_page', 15);

        return ProductResource::collection(
            $query->orderBy('name')->paginate($perPage)->withQueryString()
        );
    }
}
