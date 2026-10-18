<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\Course;
use App\Models\Event;
use App\Models\Product;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductSyncService
{
    /** @var array<class-string<Model>, string> */
    public const PRODUCTABLE_MODELS = [
        Camp::class => 'camp',
        Course::class => 'course',
        Event::class => 'event',
        Workshop::class => 'workshop',
    ];

    public function supports(Model $model): bool
    {
        return isset(self::PRODUCTABLE_MODELS[$model::class]);
    }

    public function sync(Model $model): Product
    {
        if (! $this->supports($model)) {
            throw new InvalidArgumentException('Unsupported productable model: '.$model::class);
        }

        try {
            return DB::transaction(function () use ($model): Product {
                $title = $this->titleFor($model);
                $product = $this->findProduct($model);

                if (! $product) {
                    $product = new Product([
                        'title' => $title,
                        'status' => 'draft',
                        'productable_type' => self::PRODUCTABLE_MODELS[$model::class],
                        'productable_id' => $model->getKey(),
                    ]);
                    $product->save();

                    return $product;
                }

                return $this->refreshProduct($product, $model, $title);
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent observer may have inserted the same productable first.
            $product = $this->findProduct($model);
            if (! $product) {
                throw $exception;
            }

            return $this->refreshProduct($product, $model, $this->titleFor($model));
        }
    }

    private function findProduct(Model $model): ?Product
    {
        return Product::withTrashed()
            ->where('productable_type', self::PRODUCTABLE_MODELS[$model::class])
            ->where('productable_id', $model->getKey())
            ->orderBy('id')
            ->first();
    }

    private function titleFor(Model $model): string
    {
        $title = trim((string) ($model->getAttribute('title') ?? $model->getAttribute('name') ?? ''));

        return $title !== '' ? $title : class_basename($model).' #'.$model->getKey();
    }

    private function refreshProduct(Product $product, Model $model, string $title): Product
    {
        if ($product->trashed() && ! $model->trashed()) {
            $product->restore();
        }

        if ($product->title !== $title) {
            $product->title = $title;
            $product->save();
        }

        return $product;
    }

    public function deactivate(Model $model): void
    {
        if (! $this->supports($model)) {
            return;
        }

        Product::query()
            ->where('productable_type', self::PRODUCTABLE_MODELS[$model::class])
            ->where('productable_id', $model->getKey())
            ->delete();
    }
}
