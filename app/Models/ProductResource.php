<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $amount
 * @property string|null $cost_type
 * @property string|null $amount_decimal
 * @property string|null $currency_code
 * @property Carbon|null $month
 * @property int|null $allocated_percentage
 * @property string $resourceable_type
 * @property int $resourceable_id
 */
class ProductResource extends Model
{
    use HasFactory;
    use HasPanelSearchTitle;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name', 'cost_type', 'amount', 'amount_decimal', 'currency_code', 'month', 'resourceable_type', 'resourceable_id', 'allocated_percentage',
    ];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount_decimal' => 'decimal:2'];
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProductResourceAllocation::class);
    }

    public function allocationSnapshots(): HasMany
    {
        return $this->hasMany(ProductCostAllocationSnapshot::class)->orderByDesc('version')->orderByDesc('id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProductCost::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
