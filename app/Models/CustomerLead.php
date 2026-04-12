<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'full_name', 'email', 'phone', 'source', 'topic_interest', 'product_interest', 'status', 'last_contacted_at'
    ];


}