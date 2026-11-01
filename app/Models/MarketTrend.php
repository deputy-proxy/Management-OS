<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class MarketTrend extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'data_status' => 'present',
        'source_type' => 'unknown',
        'freshness_status' => 'unknown',
        'verification_status' => 'unverified',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'category',
        'metric_key',
        'market_intelligence_metric_id',
        'value',
        'unit',
        'market',
        'market_intelligence_market_id',
        'segment',
        'market_intelligence_segment_id',
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
        'data_status',
        'source_type',
        'freshness_status',
        'verification_status',
        'source_reference',
        'source_idempotency_key',
        'source_import_run_id',
        'source_revision',
        'source_metadata',
        'missing_reason',
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
            'source_metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $marketTrend): void {
            try {
                $rawPeriodStart = $marketTrend->getAttributes()['period_start'] ?? null;
                $rawPeriodEnd = $marketTrend->getAttributes()['period_end'] ?? null;
                $periodStartDate = is_string($rawPeriodStart) ? substr($rawPeriodStart, 0, 10) : '';
                $periodEndDate = is_string($rawPeriodEnd) ? substr($rawPeriodEnd, 0, 10) : '';
                if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $periodStartDate) !== 1 || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $periodEndDate) !== 1) {
                    throw new InvalidArgumentException('Market trend period dates must use YYYY-MM-DD format.');
                }
                $periodStart = Carbon::createFromFormat('!Y-m-d', $periodStartDate)->startOfDay();
                $periodEnd = Carbon::createFromFormat('!Y-m-d', $periodEndDate)->startOfDay();
            } catch (\Throwable) {
                throw new InvalidArgumentException('Market trend period dates must be valid dates.');
            }

            if ($periodStart->format('Y-m-d') !== $periodStartDate || $periodEnd->format('Y-m-d') !== $periodEndDate) {
                throw new InvalidArgumentException('Market trend period dates must use valid calendar dates.');
            }

            if ($periodStart->gt($periodEnd)) {
                throw new InvalidArgumentException('The market trend period start must be on or before its end.');
            }

            foreach ([
                'name' => 255, 'category' => 100, 'metric_key' => 100, 'unit' => 50,
                'market' => 100, 'segment' => 100, 'source_name' => 100,
            ] as $field => $maxLength) {
                if (($field !== 'segment' && trim((string) $marketTrend->{$field}) === '') || mb_strlen((string) $marketTrend->{$field}) > $maxLength) {
                    throw new InvalidArgumentException("Market trend {$field} is required and cannot exceed {$maxLength} characters.");
                }
            }

            foreach ([
                'value' => [14, 6],
                'previous_value' => [14, 6],
                'change_pct' => [8, 4],
            ] as $field => [$integerDigits, $scale]) {
                $value = $marketTrend->getAttributes()[$field] ?? null;
                $unsigned = ltrim((string) $value, '-');
                $whole = ltrim(explode('.', $unsigned)[0], '0');
                if ($value !== null && (! is_numeric($value) || ! is_finite((float) $value) || strlen($whole) > $integerDigits || preg_match('/\A-?\d+(?:\.\d{1,'.$scale.'})?\z/', (string) $value) !== 1)) {
                    throw new InvalidArgumentException("Market trend {$field} must be a finite decimal with at most {$scale} decimal places and within the supported range.");
                }
            }

            if (filled($marketTrend->source_url) && (mb_strlen($marketTrend->source_url) > 2048 || filter_var($marketTrend->source_url, FILTER_VALIDATE_URL) === false)) {
                throw new InvalidArgumentException('Market trend source URL must be a valid URL of at most 2048 characters.');
            }

            if (
                ($confidence = $marketTrend->getAttributes()['confidence'] ?? null) !== null
                && (! is_numeric($confidence) || ! is_finite((float) $confidence) || (float) $confidence < 0 || (float) $confidence > 1 || preg_match('/\A\d+(?:\.\d{1,4})?\z/', (string) $confidence) !== 1)
            ) {
                throw new InvalidArgumentException('Market trend confidence must be between 0 and 1.');
            }

            if (! in_array($marketTrend->data_status, ['present', 'missing'], true)) {
                throw new InvalidArgumentException('Market trend data status must be present or missing.');
            }
            if (! in_array($marketTrend->source_type, ['unknown', 'manual', 'imported', 'synthetic'], true)) {
                throw new InvalidArgumentException('Market trend source type is not supported.');
            }
            if (! in_array($marketTrend->freshness_status, ['unknown', 'current', 'stale'], true)) {
                throw new InvalidArgumentException('Market trend freshness status is not supported.');
            }
            if (! in_array($marketTrend->verification_status, ['unverified', 'verified'], true)) {
                throw new InvalidArgumentException('Market trend verification status is not supported.');
            }

            if ($marketTrend->data_status === 'missing') {
                if ($marketTrend->value !== null || trim((string) $marketTrend->missing_reason) === '') {
                    throw new InvalidArgumentException('Missing observations require no numeric value and a missing-data reason.');
                }
                if ($marketTrend->confidence !== null) {
                    throw new InvalidArgumentException('Missing observations cannot carry a numeric confidence value.');
                }
            } elseif ($marketTrend->value === null || filled($marketTrend->missing_reason)) {
                throw new InvalidArgumentException('Present observations require a numeric value and cannot have a missing-data reason.');
            }

            if ($marketTrend->source_type === 'synthetic' && $marketTrend->verification_status === 'verified') {
                throw new InvalidArgumentException('Synthetic observations cannot be marked as source-verified.');
            }
            if ($marketTrend->source_type === 'imported' && blank($marketTrend->source_reference)) {
                throw new InvalidArgumentException('Imported observations require a stable source reference.');
            }
            if ($marketTrend->verification_status === 'verified' && blank($marketTrend->source_reference) && blank($marketTrend->source_url)) {
                throw new InvalidArgumentException('Verified observations require a source URL or stable source reference.');
            }

            if ($marketTrend->segment === null) {
                $duplicate = static::query()
                    ->where('metric_key', $marketTrend->metric_key)
                    ->where('market', $marketTrend->market)
                    ->whereNull('segment')
                    ->whereDate('period_start', $periodStart->toDateString())
                    ->whereDate('period_end', $periodEnd->toDateString())
                    ->where('source_name', $marketTrend->source_name)
                    ->when($marketTrend->exists, fn ($query) => $query->whereKeyNot($marketTrend->getKey()))
                    ->exists();
                if ($duplicate) {
                    throw new InvalidArgumentException('A market trend observation with this identity already exists.');
                }
            }

            $metric = $marketTrend->market_intelligence_metric_id
                ? MarketIntelligenceMetric::query()->findOrFail($marketTrend->market_intelligence_metric_id)
                : null;

            if ($metric !== null) {
                if ($metric->key !== $marketTrend->metric_key) {
                    throw new InvalidArgumentException('The selected metric definition must match the observation metric key.');
                }
                if (! $metric->acceptsUnit((string) $marketTrend->unit)) {
                    throw new InvalidArgumentException('The observation unit is not allowed by the selected metric definition.');
                }
                $periodDays = $periodStart->diffInDays($periodEnd) + 1;
                if ($metric->max_period_days !== null && $periodDays > $metric->max_period_days) {
                    throw new InvalidArgumentException('The observation period exceeds the metric definition maximum period length.');
                }
            }

            if ($marketTrend->market_intelligence_market_id !== null) {
                $market = MarketIntelligenceMarket::query()->findOrFail($marketTrend->market_intelligence_market_id);
                if ($market->code !== $marketTrend->market) {
                    throw new InvalidArgumentException('The selected market definition must match the observation market code.');
                }
            }

            if ($marketTrend->market_intelligence_segment_id !== null) {
                $segment = MarketIntelligenceSegment::query()->findOrFail($marketTrend->market_intelligence_segment_id);
                if ($segment->code !== $marketTrend->segment) {
                    throw new InvalidArgumentException('The selected segment definition must match the observation segment code.');
                }
            }
        });
    }

    /** @return BelongsTo<MarketDataImportRun, $this> */
    public function sourceImportRun(): BelongsTo
    {
        return $this->belongsTo(MarketDataImportRun::class, 'source_import_run_id');
    }

    /** @return BelongsTo<MarketIntelligenceMetric, $this> */
    public function metricDefinition(): BelongsTo
    {
        return $this->belongsTo(MarketIntelligenceMetric::class, 'market_intelligence_metric_id');
    }

    /** @return BelongsTo<MarketIntelligenceMarket, $this> */
    public function marketDefinition(): BelongsTo
    {
        return $this->belongsTo(MarketIntelligenceMarket::class, 'market_intelligence_market_id');
    }

    /** @return BelongsTo<MarketIntelligenceSegment, $this> */
    public function segmentDefinition(): BelongsTo
    {
        return $this->belongsTo(MarketIntelligenceSegment::class, 'market_intelligence_segment_id');
    }

    /** @param Builder<MarketTrend> $query
     * @return Builder<MarketTrend>
     */
    public function scopeForMetric(Builder $query, string $metricKey): Builder
    {
        return $query->where('metric_key', $metricKey);
    }

    /** @param Builder<MarketTrend> $query
     * @return Builder<MarketTrend>
     */
    public function scopeForMarket(Builder $query, string $market): Builder
    {
        return $query->where('market', $market);
    }

    /** @param Builder<MarketTrend> $query
     * @return Builder<MarketTrend>
     */
    public function scopeForSegment(Builder $query, ?string $segment): Builder
    {
        return $query->where('segment', $segment);
    }

    /** @param Builder<MarketTrend> $query
     * @return Builder<MarketTrend>
     */
    public function scopeWithinPeriod(Builder $query, string $start, string $end): Builder
    {
        try {
            $periodStart = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $start) === 1
                ? Carbon::createFromFormat('!Y-m-d', $start)
                : false;
            $periodEnd = preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $end) === 1
                ? Carbon::createFromFormat('!Y-m-d', $end)
                : false;
            if (! $periodStart || ! $periodEnd || $periodStart->format('Y-m-d') !== $start || $periodEnd->format('Y-m-d') !== $end) {
                throw new InvalidArgumentException('Market trend filter dates must use valid YYYY-MM-DD dates.');
            }
        } catch (\Throwable $exception) {
            if ($exception instanceof InvalidArgumentException) {
                throw $exception;
            }
            throw new InvalidArgumentException('Market trend filter dates must be valid dates.', previous: $exception);
        }
        if ($periodStart->gt($periodEnd)) {
            throw new InvalidArgumentException('Market trend filter start must be on or before its end.');
        }

        return $query
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start);
    }
}
