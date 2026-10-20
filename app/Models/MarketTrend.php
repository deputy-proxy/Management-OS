<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class MarketTrend extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'category',
        'metric_key',
        'value',
        'unit',
        'market',
        'segment',
        'period_start',
        'period_end',
        'source_name',
        'source_url',
        'observed_at',
        'previous_value',
        'change_pct',
        'confidence',
        'methodology',
        'metadata',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:6',
            'previous_value' => 'decimal:6',
            'change_pct' => 'decimal:4',
            'confidence' => 'decimal:4',
            'period_start' => 'date',
            'period_end' => 'date',
            'observed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $marketTrend): void {
            if (Carbon::parse((string) $marketTrend->period_start)->gt(Carbon::parse((string) $marketTrend->period_end))) {
                throw new InvalidArgumentException('The market trend period start must be on or before its end.');
            }

            if (
                $marketTrend->confidence !== null
                && ((float) $marketTrend->confidence < 0 || (float) $marketTrend->confidence > 1)
            ) {
                throw new InvalidArgumentException('Market trend confidence must be between 0 and 1.');
            }
        });
    }

    /** @param Builder<MarketTrend> $query
     *  @return Builder<MarketTrend>
     */
    public function scopeForMetric(Builder $query, string $metricKey): Builder
    {
        return $query->where('metric_key', $metricKey);
    }

    /** @param Builder<MarketTrend> $query
     *  @return Builder<MarketTrend>
     */
    public function scopeForMarket(Builder $query, string $market): Builder
    {
        return $query->where('market', $market);
    }

    /** @param Builder<MarketTrend> $query
     *  @return Builder<MarketTrend>
     */
    public function scopeForSegment(Builder $query, ?string $segment): Builder
    {
        return $query->where('segment', $segment);
    }

    /** @param Builder<MarketTrend> $query
     *  @return Builder<MarketTrend>
     */
    public function scopeWithinPeriod(Builder $query, string $start, string $end): Builder
    {
        return $query
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start);
    }
}
