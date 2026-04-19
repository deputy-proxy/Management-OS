<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestSubmissionAnswer extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'test_submission_id', 'test_question_id', 'answer', 'points', 'graded_by'
    ];

    public function testSubmission(): BelongsTo
    {
        return $this->belongsTo(TestSubmission::class);
    }

    public function testQuestion(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class);
    }
}