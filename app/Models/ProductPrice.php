<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductPrice extends Model implements Commentable
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'price', 'currency', 'priceable_type', 'priceable_id', 'price_type', 'valid_from', 'valid_until', 'legacy_source_type', 'legacy_source_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function scopeEffectiveAt(Builder $query, DateTimeInterface|string $date): Builder
    {
        return $query
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date);
            })
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            });
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
