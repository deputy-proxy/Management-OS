<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property Carbon $expected_on
 * @property string $amount
 * @property string $direction
 * @property string $description
 * @property string $currency_code
 * @property string $confidence
 * @property string $status
 * @property string|null $source_reference
 */
class CashForecastEntry extends Model
{
    protected $fillable = [
        'cash_forecast_scenario_id', 'direction', 'description', 'expected_on',
        'amount', 'currency_code', 'confidence', 'status', 'source_reference',
    ];

    protected function casts(): array
    {
        return ['expected_on' => 'date', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $assertDraft = static function (self $entry): void {
            if ($entry->scenario()->value('status') !== CashForecastScenario::STATUS_DRAFT) {
                throw new LogicException('Cash forecast entries can only be changed in a draft scenario.');
            }
        };
        static::saving($assertDraft);
        static::deleting($assertDraft);
    }

    /** @return BelongsTo<CashForecastScenario, $this> */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(CashForecastScenario::class, 'cash_forecast_scenario_id');
    }
}
