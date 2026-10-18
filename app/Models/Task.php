<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Task extends Model implements Commentable
{
    use HasPanelSearchTitle;

    use HasComments;
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['action', 'description', 'assigned_to', 'project_id', 'project_phase_id', 'status', 'due_date', 'taskable_id', 'taskable_type', 'created_by'];

    protected $casts = ['due_date' => 'date'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by = Auth::id();
        });
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectPhase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['assigned_to', 'project_id', 'project_phase_id', 'status', 'due_date', 'taskable_id', 'taskable_type', 'created_by'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
