<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class JobDescription extends Model implements Commentable
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'job_title',
        'job_level',
        'job_role',
        'relevant_indicators',
        'document_code',
        'document',
        'line_manager_id',
    ];

    protected function casts(): array
    {
        return ['relevant_indicators' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (JobDescription $jobDescription): void {
            $managerId = $jobDescription->line_manager_id;

            if ($managerId === null) {
                return;
            }

            if ($jobDescription->exists && (int) $managerId === (int) $jobDescription->getKey()) {
                throw ValidationException::withMessages(['line_manager_id' => 'A job description cannot manage itself.']);
            }

            $visited = [$jobDescription->getKey()];
            $currentId = (int) $managerId;

            while ($currentId > 0) {
                if (in_array($currentId, $visited, true)) {
                    throw ValidationException::withMessages(['line_manager_id' => 'This line-manager assignment would create a circular management chain.']);
                }

                $visited[] = $currentId;
                $nextId = static::withTrashed()->whereKey($currentId)->value('line_manager_id');

                if ($nextId === null) {
                    break;
                }

                $currentId = (int) $nextId;
            }
        });
    }

    public function lineManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'line_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'line_manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'job_description');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['job_title', 'job_level', 'job_role', 'line_manager_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
