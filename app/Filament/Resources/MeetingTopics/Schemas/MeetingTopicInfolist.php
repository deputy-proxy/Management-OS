<?php

namespace App\Filament\Resources\MeetingTopics\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;

class MeetingTopicInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('title'),
                \Filament\Infolists\Components\TextEntry::make('meeting_id'),
                \Filament\Infolists\Components\TextEntry::make('description'),
                \Filament\Infolists\Components\TextEntry::make('attachment'),
                \Filament\Infolists\Components\KeyValueEntry::make('outcome'),
            ]);
    }
}
