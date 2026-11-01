<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CustomerAcquisitionCostEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'incurred_on' => 'date',
            'amount_decimal' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Acquisition cost evidence is immutable; record a correcting entry.'));
        static::deleting(fn () => throw new LogicException('Acquisition cost evidence is retained and cannot be deleted.'));
    }
}
