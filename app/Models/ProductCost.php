<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCost extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'product_id', 'product_resource_id', 'month',
        'allocation_percent', 'resource_amount', 'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productResource(): BelongsTo
    {
        return $this->belongsTo(ProductResource::class);
    }
}