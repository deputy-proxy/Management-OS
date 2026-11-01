<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleAdsImportBatch extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'configuration_id', 'account_customer_id', 'period_start', 'period_end',
        'source_name', 'source_version', 'status', 'row_count', 'error_code',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'row_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsImportConfiguration::class, 'configuration_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(GoogleAdsCampaignDailyMetric::class, 'import_batch_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GoogleAdsCampaignDailyMetricRevision::class, 'import_batch_id');
    }
}
