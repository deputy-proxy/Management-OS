<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;


class Task extends Model implements Commentable
{
    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;

    /** @var array<int, string> */
    protected $fillable = ['action', 'description', 'assigned_to', 'project_id', 'project_phase_id', 'status', 'due_date', 'taskable_id', 'taskable_type', 'created_by'];
    protected $casts = ['due_date' => 'date'];

    public static function boot()
 	{
 	 	parent::boot();
 
 	 	static::creating(function($model)
 	 	{
 	 	 	$model->created_by=Auth::id();
 	 	});
 	}
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class);
    }

    public function projectPhase(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}