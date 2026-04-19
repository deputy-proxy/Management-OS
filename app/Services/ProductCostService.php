<?php

namespace App\Services;

use App\Models\ProductCost;
use App\Models\ProductResource;

class ProductCostService
{
    public function recalculateForResource(ProductResource $resource): void
    {
        ProductCost::where('product_resource_id', $resource->id)->delete();

        $allocations = $resource->allocations()->get();

        $totalAllocated = 0;

        foreach ($allocations as $allocation) {
            ProductCost::create([
                'product_id'          => $allocation->product_id,
                'product_resource_id' => $resource->id,
                'month'               => $resource->month,
                'allocation_percent'  => $allocation->allocation_percent,
                'resource_amount'     => $resource->amount,
                'allocated_amount'    => intval(round($resource->amount * $allocation->allocation_percent / 100)),
            ]);

            $totalAllocated += $allocation->allocation_percent;
        }

        $resource->withoutEvents(function () use ($resource, $totalAllocated) {
            $resource->update(['allocated_percentage' => $totalAllocated]);
        });
    }
}