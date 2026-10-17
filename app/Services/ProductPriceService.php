<?php

namespace App\Services;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Model;

class ProductPriceService
{
    /**
     * Return the latest price effective on the requested date, if one exists.
     * A price is effective when its optional validity bounds include that date.
     */
    public function effectivePrice(Model $product, string $date): ?ProductPrice
    {
        return $product->productPrices()
            ->effectiveAt($date)
            ->orderByRaw('valid_from IS NULL')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }
}