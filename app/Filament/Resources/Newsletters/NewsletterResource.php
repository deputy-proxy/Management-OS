<?php

namespace App\Filament\Resources\Newsletters;

use App\Models\Newsletter;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Newsletters\Pages;
use App\Filament\Resources\Newsletters\Schemas\NewsletterForm;
use App\Filament\Resources\Newsletters\Schemas\NewsletterInfolist;
use App\Filament\Resources\Newsletters\Tables\NewslettersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class NewsletterResource extends Resource
{
    protected static ?int $navigationSort = 5;
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
    use HasHexaLite;

    protected static ?string $model = Newsletter::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'newsletter.index' => __('Allows viewing the newsletter list'),
            'newsletter.create' => __('Allows creating a new newsletter'),
            'newsletter.update' => __('Allows updating newsletters'),
            'newsletter.delete' => __('Allows deleting newsletters'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewslettersTable::configure($table);
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
            'index' => Pages\ListNewsletters::route('/'),
            'create' => Pages\CreateNewsletter::route('/create'),
            'edit' => Pages\EditNewsletter::route('/{record}/edit'),
            'view' => Pages\ViewNewsletter::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('newsletter.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('newsletter.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create newsletter.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('newsletter.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('newsletter.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a newsletter.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('newsletter.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete newsletters.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
