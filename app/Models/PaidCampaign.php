<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaidCampaign extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'title', 'description', 'network', 'scheduled_to_start_on', 'started_on', 'scheduled_to_end_on', 'ended_on', 'budget', 'status'
    ];


}