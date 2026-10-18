<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ProductReview extends Model implements Commentable
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @var array<int, string> */
    protected $fillable = [
        'product_type', 'product_id', 'reviewer_name', 'reviewer_email', 'customer_id', 'content', 'rating', 'reviewed_on', 'privacy_consented_at', 'status',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewedProduct(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'product_type', 'product_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }
}
