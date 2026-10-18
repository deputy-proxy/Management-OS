<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use App\Models\Concerns\HasTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Camp extends Model
{
    use HasComments;
    use HasFactory;
    use HasPanelSearchTitle;
    use HasTasks;
    use LogsActivity;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = ['title', 'description', 'location', 'start_on', 'end_on'];

    public function productPrice(): MorphOne
    {
        return $this->morphOne(ProductPrice::class, 'priceable')->latestOfMany();
    }

    public function productPrices(): MorphMany
    {
        return $this->morphMany(ProductPrice::class, 'priceable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(ProductReview::class, 'reviewedProduct', 'product_type', 'product_id');
    }

    public function product(): MorphOne
    {
        return $this->morphOne(Product::class, 'productable');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([])->logOnlyDirty()->logEmptyChanges();
    }
}
