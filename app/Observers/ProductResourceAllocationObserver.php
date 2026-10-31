<?php

namespace App\Observers;

use App\Models\ProductResource;
use App\Models\ProductResourceAllocation;
use App\Services\ProductCostService;

class ProductResourceAllocationObserver
{
    public function __construct(protected ProductCostService $service) {}

    public function created(ProductResourceAllocation $allocation): void
    {
        $this->recalculate($allocation);
    }

    public function updated(ProductResourceAllocation $allocation): void
    {
        $this->recalculate($allocation);
    }

    public function deleted(ProductResourceAllocation $allocation): void
    {
        $this->recalculate($allocation);
    }

    public function restored(ProductResourceAllocation $allocation): void
    {
        $this->recalculate($allocation);
    }

    private function recalculate(ProductResourceAllocation $allocation): void
    {
        $resource = ProductResource::withTrashed()->find($allocation->product_resource_id);

        if ($resource) {
            $this->service->recalculateForResource($resource);
        }
    }
}
