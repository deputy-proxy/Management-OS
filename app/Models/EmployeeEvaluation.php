<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EmployeeEvaluation extends Model implements Commentable
{
    private bool $workflowStatusTransition = false;

    protected static function booted(): void
    {
        static::updating(function (EmployeeEvaluation $evaluation): void {
            if ($evaluation->isDirty('status') && ! $evaluation->workflowStatusTransition) {
                throw ValidationException::withMessages(['status' => 'Evaluation status can only change through the evaluation workflow.']);
            }
        });
    }

    /** @param array<string, mixed> $attributes */
    public function transitionTo(string $status, array $attributes = []): void
    {
        $allowed = match ($this->status) {
            'Draft' => ['In Progress', 'Submitted'],
            'In Progress' => ['Submitted'],
            'Submitted' => ['Approved'],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => sprintf('The transition from %s to %s is not allowed.', $this->status, $status)]);
        }

        if ($status === 'Submitted' && (
            ! isset($attributes['final_score'])
            || ! isset($attributes['submitted_at'])
            || ! isset($attributes['approval_token_hash'])
            || ! isset($attributes['approval_deadline_at'])
        )) {
            throw ValidationException::withMessages(['status' => 'Submitting an evaluation requires a calculated score, submission timestamp, and acknowledgment deadline.']);
        }

        if ($status === 'Approved' && (
            ! isset($attributes['approved_at'])
            || ! in_array($attributes['approval_source'] ?? null, ['employee_acknowledged', 'auto_approved_timeout'], true)
        )) {
            throw ValidationException::withMessages(['status' => 'Approval must record a timestamp and a valid approval source.']);
        }

        $this->workflowStatusTransition = true;

        try {
            $this->forceFill([...$attributes, 'status' => $status])->save();
        } finally {
            $this->workflowStatusTransition = false;
        }
    }

    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'employee_id',
        'evaluation_year',
        'criteria',
        'due_by',
        'performed_on',
        'performed_by',
        'employee_evaluation_form_id',
        'status',
        'final_score',
        'submitted_at',
        'approved_at',
        'acknowledged_at',
        'approval_deadline_at',
        'approval_source',
        'approval_comments',
        'approval_token_hash',
        'approval_token_encrypted',
        'approval_token_expires_at',
        'initiation_email_sent_at',
        'submission_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'evaluation_year' => 'integer',
            'final_score' => 'decimal:2',
            'due_by' => 'date',
            'performed_on' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'approval_deadline_at' => 'datetime',
            'approval_token_expires_at' => 'datetime',
            'initiation_email_sent_at' => 'datetime',
            'submission_email_sent_at' => 'datetime',
            'approval_token_encrypted' => 'encrypted',
        ];
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function employeeEvaluationForm(): BelongsTo
    {
        return $this->belongsTo(EmployeeEvaluationForm::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['due_by', 'performed_on', 'status', 'evaluation_year', 'final_score', 'approved_at', 'approval_source'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
