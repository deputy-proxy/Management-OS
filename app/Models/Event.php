<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Activity;

class Event extends Model implements Commentable
{
    use LogsActivity;

    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

/** @var array<int, string> */
    protected $fillable = ['title', 'description', 'start_date', 'end_date', 'start_time', 'end_time', 'event_price_id', 'featured_image'];

    public function eventPrice(): BelongsTo
    {
        return $this->belongsTo(EventPrice::class);
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }

    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }


}