<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PayrollExceptionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['payroll_exception_id', 'actor_id', 'event_type', 'note', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Payroll exception history is immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Payroll exception history cannot be deleted.');
        });
    }

    public function exception(): BelongsTo
    {
        return $this->belongsTo(PayrollException::class, 'payroll_exception_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
