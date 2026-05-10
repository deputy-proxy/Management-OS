<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class TestQuestion extends Model implements Commentable
{
    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    /** @var array<int, string> */
    protected $fillable = [
        'question',
        'question_type',
        'description',
        'correct_answer',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'data'           => 'array',
            'correct_answer' => 'array', // supports single or multiple correct answers
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_question_pivot')
            ->withPivot('points', 'order')
            ->orderByPivot('order')
            ->withTimestamps();
    }

    public function submissionAnswers(): HasMany
    {
        return $this->hasMany(TestSubmissionAnswer::class);
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