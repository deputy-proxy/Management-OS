<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\Course;
use App\Models\Event;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProductsOverviewData
{
    /** @var array<string, class-string> */
    private const PRODUCTABLE_TYPES = [
        'camp' => Camp::class,
        'course' => Course::class,
        'event' => Event::class,
        'workshop' => Workshop::class,
    ];

    /**
     * @return array{as_of: string, total: int, with_effective_price: int, missing_price: int, ambiguous_price: int, missing_productable: int, products: Collection<int, array{id: int, title: string, status: string, type: string|null, type_label: string, productable_id: int, productable_title: mixed, productable_exists: bool, price_state: string, price: mixed, currency: mixed, availability: string, valid_from: string|null, valid_until: string|null}>, statuses: array<int, string>, types: array<string, string>}
     */
    public function summary(
        ?CarbonImmutable $now = null,
        ?string $statusFilter = null,
        ?string $typeFilter = null,
        ?string $priceFilter = null,
    ): array {
        $now ??= CarbonImmutable::now(config('app.timezone'));
        $date = $now->toDateString();
        $products = Product::query()->with('productable')->orderBy('title')->orderBy('id')->get();

        $priceableIds = $products
            ->filter(fn (Product $product): bool => $product->productable !== null && isset(self::PRODUCTABLE_TYPES[$product->productable_type]))
            ->groupBy('productable_type')
            ->map(fn (Collection $group): array => $group->pluck('productable_id')->unique()->values()->all());

        $pricesByOwner = collect();
        foreach ($priceableIds as $type => $ids) {
            $pricesByOwner = $pricesByOwner->merge(
                ProductPrice::query()
                    ->where('priceable_type', $type)
                    ->whereIn('priceable_id', $ids)
                    ->get()
                    ->groupBy(fn (ProductPrice $price): string => $price->priceable_type.':'.$price->priceable_id)
            );
        }

        $rows = $products->map(function (Product $product) use ($pricesByOwner, $date): array {
            $type = $product->productable_type;
            $owner = $product->productable;
            $ownerKey = $type.':'.$product->productable_id;
            $ownerPrices = $pricesByOwner->get($ownerKey, collect());
            $effectivePrices = $ownerPrices->filter(function (ProductPrice $price) use ($date): bool {
                return ($price->valid_from === null || $price->valid_from->toDateString() <= $date)
                    && ($price->valid_until === null || $price->valid_until->toDateString() >= $date);
            })->values();

            $priceState = match (true) {
                $owner === null => 'unavailable product record',
                $effectivePrices->count() > 1 => 'ambiguous',
                $effectivePrices->count() === 1 => 'available',
                default => 'missing',
            };
            $price = $effectivePrices->count() === 1 ? $effectivePrices->first() : null;

            return [
                'id' => $product->id,
                'title' => $product->title,
                'status' => (string) ($product->status ?? ''),
                'type' => $type,
                'type_label' => self::typeLabel($type),
                'productable_id' => $product->productable_id,
                'productable_title' => $owner?->title ?? $owner?->name ?? null,
                'productable_exists' => $owner !== null,
                'price_state' => $priceState,
                'price' => $price?->price,
                'currency' => $price?->currency,
                'availability' => self::availability($owner, $date),
                'valid_from' => $price?->valid_from?->toDateString(),
                'valid_until' => $price?->valid_until?->toDateString(),
            ];
        });

        $filtered = $rows->filter(function (array $row) use ($statusFilter, $typeFilter, $priceFilter): bool {
            return ($statusFilter === null || $statusFilter === '' || $row['status'] === $statusFilter)
                && ($typeFilter === null || $typeFilter === '' || $row['type'] === $typeFilter)
                && ($priceFilter === null || $priceFilter === '' || $row['price_state'] === $priceFilter);
        })->values();

        return [
            'as_of' => $now->format('F j, Y'),
            'total' => $rows->count(),
            'with_effective_price' => $rows->where('price_state', 'available')->count(),
            'missing_price' => $rows->where('price_state', 'missing')->count(),
            'ambiguous_price' => $rows->where('price_state', 'ambiguous')->count(),
            'missing_productable' => $rows->where('productable_exists', false)->count(),
            'products' => $filtered,
            'statuses' => $rows->pluck('status')->map(fn ($status): string => (string) $status)->filter(fn (string $status): bool => $status !== '')->unique()->sort()->values()->all(),
            'types' => collect(self::PRODUCTABLE_TYPES)->mapWithKeys(fn (string $class, string $type): array => [$type => self::typeLabel($type)])->all(),
        ];
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'camp' => 'Camp',
            'course' => 'Course',
            'event' => 'Event',
            'workshop' => 'Workshop',
            default => $type !== '' ? str($type)->headline()->toString() : 'Unknown type',
        };
    }

    private static function availability(?object $owner, string $date): string
    {
        if ($owner === null) {
            return 'Unavailable: linked record missing';
        }

        $start = $owner->start_on ?? $owner->start_date ?? null;
        $end = $owner->end_on ?? $owner->end_date ?? null;
        if ($start === null || $end === null) {
            return 'Not defined by model';
        }

        $start = CarbonImmutable::parse($start)->toDateString();
        $end = CarbonImmutable::parse($end)->toDateString();

        return match (true) {
            $date < $start => 'Upcoming',
            $date > $end => 'Ended',
            default => 'In date range',
        };
    }
}
