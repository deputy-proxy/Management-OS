<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Student extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'customer_id', 'phone_number', 'email', 'address', 'full_name'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}