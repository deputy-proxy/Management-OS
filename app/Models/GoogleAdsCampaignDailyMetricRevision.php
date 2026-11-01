<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAdsCampaignDailyMetricRevision extends Model
{
    public $timestamps = false;

    protected $fillable = ['metric_id', 'import_batch_id', 'previous_values', 'revised_at'];

    protected function casts(): array
    {
        return ['previous_values' => 'array', 'revised_at' => 'immutable_datetime'];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsCampaignDailyMetric::class, 'metric_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(GoogleAdsImportBatch::class, 'import_batch_id');
    }
}
