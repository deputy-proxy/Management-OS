<?php

namespace App\Models;

use App\Models\Concerns\HasPanelSearchTitle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Activity;

/**
 * @property string|null $amount_decimal
 * @property string|null $currency_code
 */
class ProductRevenue extends Model
{
    use HasFactory;
    use HasPanelSearchTitle;
    use SoftDeletes;

    /** @var array<int, string> */
    protected $fillable = [
        'product_id', 'customer_invoice_id', 'month', 'amount', 'amount_decimal', 'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount_decimal' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customerInvoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
