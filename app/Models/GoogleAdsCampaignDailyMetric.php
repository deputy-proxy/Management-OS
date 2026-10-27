<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleAdsCampaignDailyMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_customer_id', 'campaign_id', 'campaign_name', 'metric_date', 'impressions',
        'clicks', 'cost_micros', 'conversions', 'currency_code', 'api_version', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'cost_micros' => 'integer',
            'conversions' => 'decimal:6',
            'imported_at' => 'immutable_datetime',
        ];
    }
}
