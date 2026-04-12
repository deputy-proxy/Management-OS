<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['title', 'description', 'start_date', 'end_date', 'start_time', 'end_time', 'event_price_id', 'featured_image'];

    public function eventPrice(): BelongsTo
    {
        return $this->belongsTo(EventPrice::class);
    }
}