<?php

namespace App\Filament\Resources\PaidCampaigns\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class PaidCampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('network'),
                \Filament\Infolists\Components\TextEntry::make('scheduled_to_start_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('started_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('scheduled_to_end_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('ended_on')->dateTime(),
                \Filament\Infolists\Components\TextEntry::make('budget'),
                \Filament\Infolists\Components\TextEntry::make('status'),
            ]);
    }
}
