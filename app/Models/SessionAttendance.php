<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SessionAttendance extends Model implements Commentable
{
    use HasPanelSearchTitle;

    use HasComments;
    use HasFactory;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @var array<int, string> */
    protected $fillable = [
        'group_session_id', 'student_id', 'status', 'achievement_badge', 'feedback',
    ];

    public function groupSession(): BelongsTo
    {
        return $this->belongsTo(GroupSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }
}
