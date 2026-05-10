<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HasTasks;
use Spatie\Activitylog\Models\Activity;

class Test extends Model implements Commentable
{
    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    /** @var array<int, string> */
    protected $fillable = [
        'title',
        'description',
        'topic',
        'level',
        'created_by',
        'test_type_id',
        'time_limit',
        'passing_score',
    ];

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function testType(): BelongsTo
    {
        return $this->belongsTo(TestType::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(TestQuestion::class, 'test_question_pivot')
            ->withPivot('points', 'order')
            ->orderByPivot('order')
            ->withTimestamps();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TestSubmission::class);
    }

    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(TestResult::class, TestSubmission::class);
    }

    // -----------------------------------------------------------------------
    // Tasks (morphMany)
    // -----------------------------------------------------------------------

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

// -----------------------------------------------------------------------
    // Activity log
    // -----------------------------------------------------------------------

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}