<?php

namespace App\Filament\Resources\ContentTemplates;

use App\Models\ContentTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ContentTemplates\Pages;
use App\Filament\Resources\ContentTemplates\Schemas\ContentTemplateForm;
use App\Filament\Resources\ContentTemplates\Schemas\ContentTemplateInfolist;
use App\Filament\Resources\ContentTemplates\Tables\ContentTemplatesTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentTemplateResource extends Resource
{
    protected static ?string $model = ContentTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return ContentTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ContentTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentTemplatesTable::configure($table);
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
            'index' => Pages\ListContentTemplates::route('/'),
            'create' => Pages\CreateContentTemplate::route('/create'),
            'edit' => Pages\EditContentTemplate::route('/{record}/edit'),
            'view' => Pages\ViewContentTemplate::route('/{record}'),
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

