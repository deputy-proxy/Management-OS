<?php

namespace App\Filament\Resources\JobDescriptions;

use App\Models\JobDescription;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\JobDescriptions\Pages;
use App\Filament\Resources\JobDescriptions\Schemas\JobDescriptionForm;
use App\Filament\Resources\JobDescriptions\Schemas\JobDescriptionInfolist;
use App\Filament\Resources\JobDescriptions\Tables\JobDescriptionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;

class JobDescriptionResource extends Resource
{
    protected static ?string $model = JobDescription::class;
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return JobDescriptionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return JobDescriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobDescriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobDescriptions::route('/'),
            'create' => Pages\CreateJobDescription::route('/create'),
            'edit' => Pages\EditJobDescription::route('/{record}/edit'),
            'view' => Pages\ViewJobDescription::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

