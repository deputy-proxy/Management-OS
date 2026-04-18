<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampPrice extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'camp_id', 'amount', 'currency', 'start_on', 'end_on'
    ];

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }
}