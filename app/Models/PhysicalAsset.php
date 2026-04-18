<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalAsset extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'name', 'asset_tag', 'serial_number', 'category', 'location', 'status', 'condition', 'purchase_date', 'purchase_price', 'warranty_expiry_date'
    ];


}