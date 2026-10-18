<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StudentGroupMembership extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'group_id', 'started_on', 'ended_on'];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function isActiveOn(\DateTimeInterface|string $date): bool
    {
        $date = $date instanceof \DateTimeInterface
            ? Carbon::instance($date)->toDateString()
            : Carbon::parse($date)->toDateString();

        return $this->started_on->toDateString() <= $date
            && ($this->ended_on === null || $this->ended_on->toDateString() >= $date);
    }
}