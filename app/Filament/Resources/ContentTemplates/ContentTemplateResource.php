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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class ContentTemplateResource extends Resource
{
    protected static ?int $navigationSort = 3;
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
    use HasHexaLite;

    protected static ?string $model = ContentTemplate::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'content_template.index' => __('Allows viewing the content template list'),
            'content_template.create' => __('Allows creating a new content template'),
            'content_template.update' => __('Allows updating content templates'),
            'content_template.delete' => __('Allows deleting content templates'),
        ];
    }

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
            ActivitiesRelationManager::class,
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

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('content_template.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('content_template.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create content template.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('content_template.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('content_template.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a content template.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('content_template.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete content templates.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
