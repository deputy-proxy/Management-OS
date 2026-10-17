<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** Backwards-compatible adapter for event prices stored in product_prices. */
class EventPrice extends ProductPrice implements Commentable
{
    use HasPanelSearchTitle;

    protected $table = 'product_prices';

    use HasComments;
    use LogsActivity;

    protected $fillable = ['event_id', 'amount', 'currency', 'price_type'];

    protected static function booted(): void
    {
        static::addGlobalScope('event_priceable', fn (Builder $query) => $query->where('priceable_type', 'event'));
        static::creating(function (self $price): void {
            $price->priceable_type = 'event';
        });
    }

    public function setEventIdAttribute(mixed $value): void
    {
        $this->attributes['priceable_type'] = 'event';
        $this->attributes['priceable_id'] = $value;
    }

    public function getEventIdAttribute(): mixed
    {
        return $this->attributes['priceable_type'] === 'event' ? ($this->attributes['priceable_id'] ?? null) : null;
    }

    public function setAmountAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
    }

    public function getAmountAttribute(): mixed
    {
        return $this->attributes['price'] ?? null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'priceable_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}