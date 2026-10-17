<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Activity;

class ProductResource extends Model
{
    use HasPanelSearchTitle;

    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name', 'amount', 'month', 'resourceable_type', 'resourceable_id', 'allocated_percentage',
    ];
    protected function casts(): array
    {
        return ['month' => 'date'];
    }

    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProductResourceAllocation::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProductCost::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

}