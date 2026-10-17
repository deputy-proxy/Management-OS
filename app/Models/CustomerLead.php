<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CustomerLead extends Model implements Commentable
{
    use HasPanelSearchTitle;

    use HasComments;
    use HasFactory;
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

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function customerContract(): HasOne
    {
        return $this->hasOne(CustomerContract::class, 'source_customer_lead_id');
    }

    protected function casts(): array
    {
        return [
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
            'contract_sent_at' => 'datetime',
        ];
    }

    /** @var array<int, string> */
    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'full_name', 'email', 'phone', 'source', 'topic_interest', 'product_interest', 'status', 'last_contacted_at', 'converted_customer_id', 'converted_at', 'contract_document', 'contract_send_status', 'contract_sent_at', 'contract_send_error',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }
}