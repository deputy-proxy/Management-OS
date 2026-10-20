<?php

namespace App\Filament\Resources\JobDescriptions\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class JobDescriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('job_title'),
                TextEntry::make('job_level'),
                TextEntry::make('job_role'),
                TextEntry::make('lineManager.job_title')->label('Line Manager')->placeholder('Not assigned'),
                KeyValueEntry::make('relevant_indicators'),
                TextEntry::make('document_code'),
                TextEntry::make('document'),
            ]);
    }
}
