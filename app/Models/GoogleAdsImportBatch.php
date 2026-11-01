<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $next_retry_at
 * @property bool $retryable
 * @property int $retry_attempt
 * @property string|null $error_code
 * @property GoogleAdsImportConfiguration|null $configuration
 */
class GoogleAdsImportBatch extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'configuration_id', 'account_customer_id', 'period_start', 'period_end',
        'source_name', 'source_version', 'status', 'row_count', 'error_code',
        'started_at', 'completed_at', 'retry_of_batch_id', 'rows_received',
        'rows_inserted', 'rows_updated', 'rows_unchanged', 'retryable', 'retry_attempt', 'next_retry_at', 'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'row_count' => 'integer',
            'rows_received' => 'integer',
            'rows_inserted' => 'integer',
            'rows_updated' => 'integer',
            'rows_unchanged' => 'integer',
            'retryable' => 'boolean',
            'retry_attempt' => 'integer',
            'next_retry_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GoogleAdsImportConfiguration, $this> */
    public function configuration(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsImportConfiguration::class, 'configuration_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(GoogleAdsCampaignDailyMetric::class, 'import_batch_id');
    }

    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_batch_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retry_of_batch_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GoogleAdsCampaignDailyMetricRevision::class, 'import_batch_id');
    }
}
