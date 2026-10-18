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

class SessionAssistance extends Model implements Commentable
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
    protected $fillable = [
        'session_id', 'teacher_id', 'assistance_date', 'status', 'review'
    ];

    protected function casts(): array
    {
        return [
            'review' => 'array',
        ];
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
        return LogOptions::defaults()
            ->logAll();
    }

}