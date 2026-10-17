<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class Meeting extends Model
{
    use HasPanelSearchTitle;

    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'subject', 'description', 'type', 'status', 'starts_at', 'ends_at', 'location', 'organizer_id', 'participants'
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'organizer_id');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

}