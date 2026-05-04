<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePaySlip extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['employee_id', 'month', 'items', 'amount', 'due_on', 'paid_on'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}