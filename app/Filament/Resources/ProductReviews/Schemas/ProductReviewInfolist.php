<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('product_type')->label('Product type'),
                TextEntry::make('reviewedProduct.title')->label('Product')->placeholder('Product unavailable'),
                TextEntry::make('reviewer_name'),
                TextEntry::make('reviewer_email'),
                TextEntry::make('customer.name')->label('Customer'),
                TextEntry::make('content'),
                TextEntry::make('rating')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('reviewed_on')->dateTime(),
                TextEntry::make('privacy_consented_at')->dateTime(),
            ]);
    }
}
