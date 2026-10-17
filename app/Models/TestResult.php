<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class TestResult extends Model implements Commentable
{
    use HasPanelSearchTitle;

    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    /** @var array<int, string> */
    protected $fillable = [
        'test_submission_id',
        'student_id',
        'teacher_id',
        'results',
    ];

    protected function casts(): array
    {
        return [
            'results' => 'array',
            // suggested results structure:
            // {
            //   "total_points":    100,
            //   "earned_points":   82,
            //   "percentage":      82.0,
            //   "passed":          true,
            //   "feedback":        "Good work overall.",
            //   "per_question": [
            //     { "test_question_id": 1, "points_awarded": 10, "feedback": "..." },
            //     ...
            //   ]
            // }
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function testSubmission(): BelongsTo
    {
        return $this->belongsTo(TestSubmission::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function answers(): HasManyThrough
    {
        return $this->hasManyThrough(
            TestSubmissionAnswer::class,
            TestSubmission::class,
            'id',                  // foreign key on test_submissions
            'test_submission_id',  // foreign key on test_submission_answers
            'test_submission_id',  // local key on test_results
            'id'                   // local key on test_submissions
        );
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