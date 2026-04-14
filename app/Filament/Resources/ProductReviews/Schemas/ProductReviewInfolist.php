<?php

namespace App\Filament\Resources\ProductReviews\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class ProductReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('product_type'),
                \Filament\Infolists\Components\TextEntry::make('product_id'),
                \Filament\Infolists\Components\TextEntry::make('reviewer_name'),
                \Filament\Infolists\Components\TextEntry::make('reviewer_email'),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
                \Filament\Infolists\Components\TextEntry::make('content'),
                \Filament\Infolists\Components\TextEntry::make('rating'),
                \Filament\Infolists\Components\TextEntry::make('reviewed_on')->dateTime(),
            ]);
    }
}
