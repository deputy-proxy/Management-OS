<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workshop extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'description', 'start_date', 'end_date', 'start_time', 'end_time', 'type', 'location', 'workshop_price_id'
    ];

    public function workshopPrice(): BelongsTo
    {
        return $this->belongsTo(WorkshopPrice::class);
    }
}