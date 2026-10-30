<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollPeriodLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_pay_slip_id', 'actor_id', 'related_pay_slip_id', 'related_item_id',
        'event_type', 'from_status', 'to_status', 'note', 'snapshot_hash', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll period lifecycle history is immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll period lifecycle history cannot be deleted.');
        });
    }

    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlip::class, 'employee_pay_slip_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function relatedPaySlip(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlip::class, 'related_pay_slip_id');
    }

    public function relatedItem(): BelongsTo
    {
        return $this->belongsTo(EmployeePaySlipItem::class, 'related_item_id');
    }
}
