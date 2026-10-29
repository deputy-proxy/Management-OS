<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollCalculationResultItem extends Model
{
    protected $fillable = [
        'payroll_calculation_result_id', 'line_number', 'item_code', 'item_type', 'label',
        'calculation_method', 'source_parameter', 'base_item_code', 'input_value', 'rate_percent',
        'amount', 'currency_code', 'rounding_mode', 'rounding_precision', 'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'input_value' => 'decimal:6',
            'rate_percent' => 'decimal:6',
            'amount' => 'decimal:6',
            'rounding_precision' => 'integer',
            'calculation_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll calculation line items are immutable. Create a new calculation run for changed inputs.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll calculation line items cannot be deleted.');
        });
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(PayrollCalculationResult::class, 'payroll_calculation_result_id');
    }
}
