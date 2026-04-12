<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'description', 'project_manager', 'status', 'planned_start_date', 'planned_end_date', 'start_date', 'end_date', 'budget'
    ];

    public function ProjectManager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}