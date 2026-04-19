<?php

namespace App\Models\Concerns;

use App\Models\ProductResource;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasProductResources
{
    public function productResources(): MorphMany
    {
        return $this->morphMany(ProductResource::class, 'resourceable');
    }
}