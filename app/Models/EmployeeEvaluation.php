<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class EmployeeEvaluation extends Model implements Commentable
{
    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;

    /** @var array<int, string> */
    protected $fillable = ['employee_id', 'criteria', 'due_by', 'performed_on', 'performed_by'];
    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}