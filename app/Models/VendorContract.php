<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorContract extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['start_date', 'end_date', 'document', 'vendor_id', 'contract_code'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}