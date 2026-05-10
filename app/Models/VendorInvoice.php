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
use App\Models\Concerns\HasProductResources;
use Spatie\Activitylog\Models\Activity;


class VendorInvoice extends Model implements Commentable
{
    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;
    use HasTasks;
    use HasProductResources;

    public function tasks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

/** @var array<int, string> */
    protected $fillable = ['vendor_id', 'vendor_contract_id', 'amount_without_vat', 'vat', 'currency', 'month', 'status', 'invoice', 'amount', 'invoice_number', 'identifier'];

    protected function casts(): array
    {
        return ['month' => 'date'];
    }
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorContract(): BelongsTo
    {
        return $this->belongsTo(VendorContract::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }
}