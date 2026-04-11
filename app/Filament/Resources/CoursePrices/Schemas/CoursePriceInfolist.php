<?php

namespace App\Filament\Resources\CoursePrices\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class CoursePriceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('course_id'),
                \Filament\Infolists\Components\TextEntry::make('amount'),
                \Filament\Infolists\Components\TextEntry::make('start_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('end_on')->dateTime(),
            ]);
    }
}
