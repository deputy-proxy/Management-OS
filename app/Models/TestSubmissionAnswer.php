<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class TestSubmissionAnswer extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'test_submission_id',
        'test_question_id',
        'answer',
        'points',
        'graded_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'answer'   => 'array', // flexible: string, array of choices, etc.
            'points'   => 'decimal:2',
            'metadata' => 'array',
            // metadata common fields:  time_spent_seconds, answer_changed_count, skipped_and_returned
            // metadata text fields:    keystrokes, corrections, paste_count, focus_lost_count
            // metadata choice fields:  checkbox_changes, final_selection_changed
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    public function testSubmission(): BelongsTo
    {
        return $this->belongsTo(TestSubmission::class);
    }

    public function testQuestion(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'graded_by');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

}