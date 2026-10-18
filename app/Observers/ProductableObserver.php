<?php

namespace App\Observers;

use App\Services\ProductSyncService;
use Illuminate\Database\Eloquent\Model;

class ProductableObserver
{
    public function __construct(private readonly ProductSyncService $products) {}

    public function created(Model $model): void
    {
        $this->products->sync($model);
    }

    public function updated(Model $model): void
    {
        $this->products->sync($model);
    }

    public function restored(Model $model): void
    {
        $this->products->sync($model);
    }

    public function deleted(Model $model): void
    {
        $this->products->deactivate($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->products->deactivate($model);
    }
}
