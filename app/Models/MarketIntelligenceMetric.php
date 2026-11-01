<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

/**
 * @property string $key
 * @property string $name
 * @property string $category
 * @property string $value_type
 * @property array<int, string> $allowed_units
 * @property string $period_granularity
 * @property int|null $max_period_days
 * @property bool $is_active
 * @property bool $is_provisional
 */
class MarketIntelligenceMetric extends Model
{
    public const VALUE_TYPES = ['index', 'currency', 'count', 'percentage', 'ratio', 'duration', 'quantity', 'other'];

    public const PERIOD_GRANULARITIES = ['day', 'week', 'month', 'quarter', 'year', 'custom'];

    /** @var list<string> */
    protected $fillable = [
        'key', 'name', 'category', 'value_type', 'allowed_units', 'period_granularity',
        'max_period_days', 'description', 'is_active', 'is_provisional',
    ];

    protected function casts(): array
    {
        return [
            'allowed_units' => 'array',
            'max_period_days' => 'integer',
            'is_active' => 'boolean',
            'is_provisional' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $metric): void {
            if (trim((string) $metric->name) === '' || trim((string) $metric->category) === '') {
                throw new InvalidArgumentException('Metric name and category are required.');
            }
            if (! preg_match('/^[a-z][a-z0-9_]{0,99}$/', $metric->key)) {
                throw new InvalidArgumentException('Metric key must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.');
            }
            if (! in_array($metric->value_type, self::VALUE_TYPES, true)) {
                throw new InvalidArgumentException('Choose a supported metric value type.');
            }
            if (! in_array($metric->period_granularity, self::PERIOD_GRANULARITIES, true)) {
                throw new InvalidArgumentException('Choose a supported period granularity.');
            }
            $units = $metric->getAttribute('allowed_units');
            if (! is_array($units) || $units === []) {
                throw new InvalidArgumentException('At least one valid unit is required for a metric.');
            }
            foreach ($units as $unit) {
                if (! is_string($unit) || trim($unit) === '' || mb_strlen($unit) > 50) {
                    throw new InvalidArgumentException('Every metric unit must be a non-empty string of at most 50 characters.');
                }
            }
            if (count(array_unique($units)) !== count($units)) {
                throw new InvalidArgumentException('Metric units must be unique.');
            }
            if ($metric->max_period_days !== null && ($metric->max_period_days < 1 || $metric->max_period_days > 36525)) {
                throw new InvalidArgumentException('Maximum period length must be between 1 and 36525 days.');
            }
        });
    }

    public function acceptsUnit(string $unit): bool
    {
        return in_array($unit, $this->allowed_units ?? [], true);
    }

    /** @return HasMany<MarketTrend, $this> */
    public function trends(): HasMany
    {
        return $this->hasMany(MarketTrend::class, 'market_intelligence_metric_id');
    }
}
