<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

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

class Employee extends Model implements Commentable
{
    use HasPanelSearchTitle;

    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
    /** @var array<int, string> */
    protected $fillable = ['full_name', 'first_name', 'last_name', 'user_id', 'date_of_birth', 'job_description'];

    public function engagements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkerEngagement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([])->logOnlyDirty()->logEmptyChanges();
    }

    public function jobDescription(): BelongsTo
    {
        return $this->belongsTo(JobDescription::class, 'job_description');
    }
}