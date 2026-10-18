<?php

namespace App\Filament\Resources\Sessions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('session_type')->label('Type'),
            TextEntry::make('identifier'),
            TextEntry::make('group.title')->label('Group'),
            TextEntry::make('customer.name')->label('Customer'),
            TextEntry::make('course.title')->label('Course'),
            TextEntry::make('teacher.full_name')->label('Teacher'),
            TextEntry::make('starts_at')->dateTime(),
            TextEntry::make('ends_at')->dateTime(),
            TextEntry::make('status'),
            TextEntry::make('attendance_status')->label('Attendance'),
        ]);
    }
}
