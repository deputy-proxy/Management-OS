<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'competition_id', 'first_name', 'last_name', 'full_name', 'email', 'content'
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }
}