<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLeadStatusHistory extends Model
{
    /** @var array<int, string> */
    protected $fillable = ['customer_lead_id', 'from_status', 'to_status', 'changed_at', 'changed_by'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function customerLead(): BelongsTo
    {
        return $this->belongsTo(CustomerLead::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
