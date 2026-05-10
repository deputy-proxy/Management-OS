<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Activity;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'status', 'productable_type', 'productable_id',
    ];

    public function productable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resourceAllocations(): HasMany
    {
        return $this->hasMany(ProductResourceAllocation::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProductCost::class);
    }

    public function revenues(): HasMany
    {
        return $this->hasMany(ProductRevenue::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

}