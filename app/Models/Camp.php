<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Camp extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['title', 'description', 'location', 'start_on', 'end_on', 'camp_price', 'camp_price_id'];

    public function campPrice(): BelongsTo
    {
        return $this->belongsTo(CampPrice::class);
    }

    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }

}