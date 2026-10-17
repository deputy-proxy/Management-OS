<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Backwards-compatible adapter for workshop prices stored in product_prices. */
class WorkshopPrice extends ProductPrice implements Commentable
{
    protected $table = 'product_prices';

    use HasComments;
    use LogsActivity;

    protected $fillable = ['workshop_id', 'amount', 'currency', 'start_on', 'end_on', 'price_type'];

    protected static function booted(): void
    {
        static::addGlobalScope('workshop_priceable', fn (Builder $query) => $query->where('priceable_type', 'workshop'));
        static::creating(function (self $price): void {
            $price->priceable_type = 'workshop';
        });
    }

    public function setWorkshopIdAttribute(mixed $value): void
    {
        $this->attributes['priceable_type'] = 'workshop';
        $this->attributes['priceable_id'] = $value;
    }

    public function getWorkshopIdAttribute(): mixed
    {
        return $this->attributes['priceable_type'] === 'workshop' ? ($this->attributes['priceable_id'] ?? null) : null;
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

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'priceable_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}