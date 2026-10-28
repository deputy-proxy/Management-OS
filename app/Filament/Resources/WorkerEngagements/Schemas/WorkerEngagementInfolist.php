<?php

namespace App\Filament\Resources\WorkerEngagements\Schemas;

use App\Models\WorkerEngagement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WorkerEngagementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('engagement_type')->formatStateUsing(fn (?string $state): string => WorkerEngagement::TYPES[$state ?? 'unknown'] ?? 'Unknown / unresolved'),
            TextEntry::make('status')->formatStateUsing(fn (?string $state): string => WorkerEngagement::STATUSES[$state ?? 'unresolved'] ?? 'Unresolved'),
            TextEntry::make('employee.full_name')->label('Employee')->placeholder('Not linked'),
            TextEntry::make('vendor.name')->label('Vendor / contracting party')->placeholder('Not linked'),
            TextEntry::make('employeeContract.id')->label('Employee contract ID')->placeholder('Not linked'),
            TextEntry::make('vendorContract.contract_code')->label('Vendor contract')->placeholder('Not linked'),
            TextEntry::make('start_date')->date()->placeholder('Not recorded'),
            TextEntry::make('end_date')->date()->placeholder('Open-ended'),
            TextEntry::make('source_reference')->placeholder('Not recorded'),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('updated_at')->dateTime(),
        ]);
    }
}
