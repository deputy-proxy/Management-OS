<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property string $code
 * @property string $name
 * @property string $geography_type
 * @property bool $is_active
 * @property bool $is_provisional
 */
class MarketIntelligenceMarket extends Model
{
    /** @var list<string> */
    protected $fillable = ['code', 'name', 'geography_type', 'description', 'is_active', 'is_provisional'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_provisional' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $market): void {
            if (! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,39}$/', $market->code)) {
                throw new InvalidArgumentException('Market code must use uppercase letters, numbers, dots, underscores, or hyphens.');
            }
            if (! in_array($market->geography_type, ['country', 'region', 'city', 'custom', 'unspecified'], true)) {
                throw new InvalidArgumentException('Choose a supported market geography type.');
            }
            if (trim((string) $market->name) === '') {
                throw new InvalidArgumentException('Market name is required.');
            }
        });
    }

    /** @return HasMany<MarketTrend, $this> */
    public function trends(): HasMany
    {
        return $this->hasMany(MarketTrend::class, 'market_intelligence_market_id');
    }
}
