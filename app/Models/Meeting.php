<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'subject', 'description', 'type', 'status', 'starts_at', 'ends_at', 'location', 'organizer_id', 'participants'
    ];

    protected function casts(): array
    {
        return [
            'participants' => 'array',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'organizer_id');
    }

}