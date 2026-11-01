<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property bool $is_provisional
 */
class MarketIntelligenceSegment extends Model
{
    /** @var list<string> */
    protected $fillable = ['code', 'name', 'description', 'is_active', 'is_provisional'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_provisional' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $segment): void {
            if (! preg_match('/^[a-z0-9][a-z0-9._-]{0,59}$/', $segment->code)) {
                throw new InvalidArgumentException('Segment code must use lowercase letters, numbers, dots, underscores, or hyphens.');
            }
            if (trim((string) $segment->name) === '') {
                throw new InvalidArgumentException('Segment name is required.');
            }
        });
    }

    /** @return HasMany<MarketTrend, $this> */
    public function trends(): HasMany
    {
        return $this->hasMany(MarketTrend::class, 'market_intelligence_segment_id');
    }
}
