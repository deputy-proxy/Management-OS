<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class Product extends Model
{
    use HasPanelSearchTitle;

    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'status', 'productable_type', 'productable_id',
    ];

    /**
     * Normalize legacy class names stored before the product morph map was introduced.
     * Eloquent otherwise attempts to instantiate values such as "Course" as global classes.
     */
    public function getProductableTypeAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $legacyTypes = [
            'camp' => Camp::class,
            'course' => Course::class,
            'event' => Event::class,
            'workshop' => Workshop::class,
        ];

        foreach ($legacyTypes as $alias => $class) {
            if (strcasecmp($value, $alias) === 0
                || strcasecmp($value, class_basename($class)) === 0
                || strcasecmp($value, $class) === 0) {
                return $alias;
            }
        }

        return $value;
    }

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