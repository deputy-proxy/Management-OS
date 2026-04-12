<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'group_session_id', 'student_id'
    ];

    public function groupSession(): BelongsTo
    {
        return $this->belongsTo(GroupSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}