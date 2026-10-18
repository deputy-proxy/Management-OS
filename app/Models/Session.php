<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Session extends Model implements Commentable, Eventable
{
    protected $table = 'scheduled_sessions';

    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = ['session_type', 'group_id', 'customer_id', 'course_id', 'teacher_id', 'starts_at', 'ends_at', 'status', 'attendance_status', 'identifier'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public const TYPE_GROUP = 'group';

    public const TYPE_INDIVIDUAL = 'individual';

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

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

    public function sessionAttendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'session_id');
    }

    public function sessionAssistances(): HasMany
    {
        return $this->hasMany(SessionAssistance::class, 'session_id');
    }

    public function employeeTimesheets(): HasMany
    {
        return $this->hasMany(EmployeeTimesheet::class, 'session_id');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function toCalendarEvent(): CalendarEvent
    {
        $title = $this->session_type === self::TYPE_GROUP
            ? trim(($this->group?->title ?? 'Group').' - '.($this->course?->title ?? 'Course'))
            : trim(($this->course?->title ?? 'Course').' - '.($this->customer?->name ?? 'Customer'));

        return CalendarEvent::make($this)->title($title)->start($this->starts_at)->end($this->ends_at)->action('view');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}
