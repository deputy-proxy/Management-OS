<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;

class ProductResourceAllocation extends Model
{
    use HasFactory;
    use HasPanelSearchTitle;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'product_id', 'product_resource_id', 'allocation_percent', 'month',
        'targetable_type', 'targetable_id', 'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $allocation): void {
            if ($allocation->targetable_type === 'product') {
                if ($allocation->isDirty('product_id') && ! $allocation->isDirty('targetable_id')) {
                    $allocation->targetable_id = $allocation->product_id;
                } else {
                    $allocation->product_id = $allocation->targetable_id;
                }
            } elseif ($allocation->targetable_type !== null) {
                $allocation->product_id = null;
            } elseif ($allocation->product_id !== null) {
                $allocation->targetable_type = 'product';
                $allocation->targetable_id = $allocation->product_id;
            }
        });
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productResource(): BelongsTo
    {
        return $this->belongsTo(ProductResource::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
