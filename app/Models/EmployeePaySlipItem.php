<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

class EmployeePaySlipItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_pay_slip_id',
        'item_type',
        'source_type',
        'source_id',
        'period_key',
        'source_key',
        'occurred_on',
        'description',
        'quantity',
        'unit',
        'unit_rate',
        'amount',
        'currency',
        'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'quantity' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'calculation_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $paySlip = EmployeePaySlip::query()->find($item->employee_pay_slip_id);
            if (! $paySlip || ! $paySlip->isOpen()) {
                throw ValidationException::withMessages(['employee_pay_slip_id' => 'Source items can only be added to a draft, open payslip.']);
            }
        });

        static::updating(function (): never {
            throw new LogicException('Payslip source items are immutable. Add a documented adjustment instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Payslip source items cannot be deleted. Add a documented adjustment instead.');
        });
    }

    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlip::class, 'employee_pay_slip_id');
    }
}
