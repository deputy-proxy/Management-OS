<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class NewsletterSubscriberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('first_name'),
                \Filament\Infolists\Components\TextEntry::make('last_name'),
                \Filament\Infolists\Components\TextEntry::make('email'),
                \Filament\Infolists\Components\TextEntry::make('customer_id'),
            ]);
    }
}
