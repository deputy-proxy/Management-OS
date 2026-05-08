<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HasTasks;

class JobDescription extends Model implements Commentable
{
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'relevant_indicators' => 'array',
        ];
    }

    /** @var array<int, string> */
    protected $fillable = [
        'job_title', 'job_level', 'job_role', 'relevant_indicators', 'document_code', 'document'
    ];
    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }


}