<?php

namespace App\Observers;

use App\Models\ProductResource;
use App\Services\ProductCostService;

class ProductResourceObserver
{
    public function __construct(protected ProductCostService $service) {}

    public function created(ProductResource $resource): void
    {
        $this->service->recalculateForResource($resource);
    }

    public function updated(ProductResource $resource): void
    {
        $this->service->recalculateForResource($resource);
    }

    public function deleted(ProductResource $resource): void
    {
        // Keep soft-deleted source history inspectable without leaving active cost projections.
        if ($resource->exists) {
            $this->service->recalculateForResource($resource);
        }
    }

    public function restored(ProductResource $resource): void
    {
        $this->service->recalculateForResource($resource);
    }
}
