<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Activity;

use App\Models\Concerns\HasTasks;



class CustomerInvoice extends Model implements Commentable
{
    use HasPanelSearchTitle;

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
    protected $fillable = ['customer_id', 'customer_contract_id', 'amount_without_vat', 'vat', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code', 'status_code', 'month', 'status', 'invoice', 'identifier'];

    protected function casts(): array
    {
        return ['amount_without_vat_decimal' => 'decimal:2', 'vat_decimal' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerContract(): BelongsTo
    {
        return $this->belongsTo(CustomerContract::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['amount_without_vat', 'vat', 'amount_without_vat_decimal', 'vat_decimal', 'currency_code', 'status_code', 'month', 'status'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function productRevenue(): HasOne
    {
        return $this->hasOne(ProductRevenue::class);
    }
}