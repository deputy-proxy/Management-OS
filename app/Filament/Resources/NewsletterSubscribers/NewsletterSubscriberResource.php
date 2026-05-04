<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Models\NewsletterSubscriber;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\NewsletterSubscribers\Pages;
use App\Filament\Resources\NewsletterSubscribers\Schemas\NewsletterSubscriberForm;
use App\Filament\Resources\NewsletterSubscribers\Schemas\NewsletterSubscriberInfolist;
use App\Filament\Resources\NewsletterSubscribers\Tables\NewsletterSubscribersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class NewsletterSubscriberResource extends Resource
{
    protected static ?int $navigationSort = 2;
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
    use HasHexaLite;
    protected static ?string $model = NewsletterSubscriber::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'newsletter_subscriber.index' => __('Allows viewing the newsletter subscriber list'),
            'newsletter_subscriber.create' => __('Allows creating a new newsletter subscriber'),
            'newsletter_subscriber.update' => __('Allows updating newsletter subscribers'),
            'newsletter_subscriber.delete' => __('Allows deleting newsletter subscribers'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterSubscriberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterSubscriberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscribersTable::configure($table);
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
            'index' => Pages\ListNewsletterSubscribers::route('/'),
            'create' => Pages\CreateNewsletterSubscriber::route('/create'),
            'edit' => Pages\EditNewsletterSubscriber::route('/{record}/edit'),
            'view' => Pages\ViewNewsletterSubscriber::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('newsletter_subscriber.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('newsletter_subscriber.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create a newsletter subscriber.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('newsletter_subscriber.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('newsletter_subscriber.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a newsletter subscriber.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('newsletter_subscriber.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete newsletter subscribers.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}


