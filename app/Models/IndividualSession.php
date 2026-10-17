<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;

class IndividualSession extends Model
{
    use HasPanelSearchTitle;

    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['identifier', 'customer_id', 'course_id', 'teacher_id', 'starts_at', 'ends_at', 'status', 'attendance_status'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
