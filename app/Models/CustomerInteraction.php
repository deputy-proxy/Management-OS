<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['customer_id', 'initiator', 'reason', 'description', 'timestamp', 'interaction_rating'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}