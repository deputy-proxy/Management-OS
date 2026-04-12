<?php

namespace App\Filament\Resources\PaidCampaigns;

use App\Models\PaidCampaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PaidCampaigns\Pages;
use App\Filament\Resources\PaidCampaigns\Schemas\PaidCampaignForm;
use App\Filament\Resources\PaidCampaigns\Schemas\PaidCampaignInfolist;
use App\Filament\Resources\PaidCampaigns\Tables\PaidCampaignsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaidCampaignResource extends Resource
{
    protected static ?string $model = PaidCampaign::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return PaidCampaignForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaidCampaignInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaidCampaignsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaidCampaigns::route('/'),
            'create' => Pages\CreatePaidCampaign::route('/create'),
            'edit' => Pages\EditPaidCampaign::route('/{record}/edit'),
            'view' => Pages\ViewPaidCampaign::route('/{record}'),
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

