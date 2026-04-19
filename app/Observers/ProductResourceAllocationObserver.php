<?php

namespace App\Observers;

use App\Models\ProductResourceAllocation;
use App\Services\ProductCostService;

class ProductResourceAllocationObserver
{
    public function __construct(protected ProductCostService $service) {}

    public function created(ProductResourceAllocation $allocation): void
    {
        $this->service->recalculateForResource($allocation->productResource);
    }

    public function updated(ProductResourceAllocation $allocation): void
    {
        $this->service->recalculateForResource($allocation->productResource);
    }

    public function deleted(ProductResourceAllocation $allocation): void
    {
        $this->service->recalculateForResource($allocation->productResource);
    }

    public function restored(ProductResourceAllocation $allocation): void
    {
        $this->service->recalculateForResource($allocation->productResource);
    }
}