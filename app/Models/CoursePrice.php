<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Backwards-compatible adapter for course prices stored in product_prices.
 * The course_prices table is retained only for historical migration rollback.
 */
class CoursePrice extends ProductPrice implements Commentable
{
    protected $table = 'product_prices';

    use HasComments;
    use LogsActivity;

    protected $fillable = [
        'course_id', 'amount', 'start_on', 'end_on', 'currency', 'price_type',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('course_priceable', fn (Builder $query) => $query->where('priceable_type', 'course'));
        static::creating(function (self $price): void {
            $price->priceable_type = 'course';
        });
    }

    public function setCourseIdAttribute(mixed $value): void
    {
        $this->attributes['priceable_type'] = 'course';
        $this->attributes['priceable_id'] = $value;
    }

    public function getCourseIdAttribute(): mixed
    {
        return $this->attributes['priceable_type'] === 'course' ? ($this->attributes['priceable_id'] ?? null) : null;
    }

    public function setAmountAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
    }

    public function getAmountAttribute(): mixed
    {
        return $this->attributes['price'] ?? null;
    }

    public function setStartOnAttribute(mixed $value): void
    {
        $this->attributes['valid_from'] = $value;
    }

    public function getStartOnAttribute(): mixed
    {
        return $this->attributes['valid_from'] ?? null;
    }

    public function setEndOnAttribute(mixed $value): void
    {
        $this->attributes['valid_until'] = $value;
    }

    public function getEndOnAttribute(): mixed
    {
        return $this->attributes['valid_until'] ?? null;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'priceable_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}