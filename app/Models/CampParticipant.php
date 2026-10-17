<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;

class CampParticipant extends Model
{
    use HasPanelSearchTitle;

    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;
    use LogsActivity;

    /** @var array<int, string> */
    protected $fillable = [
        'camp_id', 'customer_id'
    ];

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

}