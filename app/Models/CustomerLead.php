<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CustomerLead extends Model implements Commentable
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

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function customerContract(): HasOne
    {
        return $this->hasOne(CustomerContract::class, 'source_customer_lead_id');
    }

    /** Interactions become associated with a lead once it has been converted to a customer. */
    public function customerInteractions(): HasManyThrough
    {
        return $this->hasManyThrough(CustomerInteraction::class, Customer::class, 'id', 'customer_id', 'converted_customer_id', 'id');
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'proposal' => 'Proposal',
            'converted' => 'Converted',
            'lost' => 'Lost',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CustomerLead $lead): void {
            $lead->status = strtolower(trim((string) $lead->status));
            if (blank($lead->full_name) || ($lead->exists && ($lead->isDirty('first_name') || $lead->isDirty('middle_name') || $lead->isDirty('last_name')) && ! $lead->isDirty('full_name'))) {
                $lead->full_name = collect([$lead->first_name, $lead->middle_name, $lead->last_name])->filter(fn ($name) => filled($name))->implode(' ');
            }

            if (! array_key_exists($lead->status, self::statuses())) {
                throw ValidationException::withMessages(['status' => 'Choose a valid customer lead status.']);
            }

            if ($lead->exists && $lead->getOriginal('status') === 'converted' && $lead->status !== 'converted') {
                throw ValidationException::withMessages(['status' => 'A converted lead cannot return to the active lead pipeline.']);
            }

            if ($lead->exists && $lead->isDirty('status')) {
                $transitions = [
                    'new' => ['contacted', 'qualified', 'converted', 'lost'],
                    'contacted' => ['qualified', 'proposal', 'converted', 'lost'],
                    'qualified' => ['contacted', 'proposal', 'converted', 'lost'],
                    'proposal' => ['qualified', 'converted', 'lost'],
                    'lost' => ['new'],
                    'converted' => [],
                ];

                if (! in_array($lead->status, $transitions[$lead->getOriginal('status')] ?? [], true)) {
                    throw ValidationException::withMessages(['status' => 'This customer lead status transition is not allowed.']);
                }
            }

            if ($lead->status === 'converted' && (! $lead->converted_customer_id || ! $lead->converted_at)) {
                throw ValidationException::withMessages(['status' => 'Only the lead conversion workflow can mark a lead as converted.']);
            }
        });
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
        'first_name', 'middle_name', 'last_name', 'full_name', 'email', 'phone', 'source', 'topic_interest', 'product_interest', 'status', 'last_contacted_at', 'converted_customer_id', 'converted_at', 'contract_document', 'contract_send_status', 'contract_sent_at', 'contract_send_error', 'privacy_consented_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll();
    }
}
