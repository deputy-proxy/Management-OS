<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Services\Academic\AcademicSessionLinkValidator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class ProgressUpdate extends Model implements Commentable
{

    use HasPanelSearchTitle;

    use LogsActivity;

    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    protected static function booted(): void
    {
        static::saving(function (ProgressUpdate $record): void {
            app(AcademicSessionLinkValidator::class)->validate(
                $record->session_id === null ? null : (int) $record->session_id,
                $record->student_id === null ? null : (int) $record->student_id,
            );
        });
    }


    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

/** @var array<int, string> */
    protected $fillable = [
        'student_id', 'teacher_id', 'session_id', 'update', 'update_date'
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['update_date'])->logOnlyDirty()->dontLogEmptyChanges();
    }

}