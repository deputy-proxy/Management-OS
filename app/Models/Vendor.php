<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HasTasks;


class Vendor extends Model implements Commentable
{
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;
    use LogsActivity;

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }
    

    /** @var array<int, string> */
    protected $fillable = ['name', 'vendor_category_id', 'start_date', 'end_date', 'website'];

    public function vendorCategory(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class);
    }
}