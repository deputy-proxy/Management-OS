<?php

namespace App\Filament\Resources\SocialPosts;

use App\Models\SocialPost;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\SocialPosts\Pages;
use App\Filament\Resources\SocialPosts\Schemas\SocialPostForm;
use App\Filament\Resources\SocialPosts\Schemas\SocialPostInfolist;
use App\Filament\Resources\SocialPosts\Tables\SocialPostsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class SocialPostResource extends Resource
{
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
    use HasHexaLite;

    protected static ?string $model = SocialPost::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'social_post.index' => __('Allows viewing the social post list'),
            'social_post.create' => __('Allows creating a new social post'),
            'social_post.update' => __('Allows updating social posts'),
            'social_post.delete' => __('Allows deleting social posts'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return SocialPostForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SocialPostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialPostsTable::configure($table);
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
            'index' => Pages\ListSocialPosts::route('/'),
            'create' => Pages\CreateSocialPost::route('/create'),
            'edit' => Pages\EditSocialPost::route('/{record}/edit'),
            'view' => Pages\ViewSocialPost::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('social_post.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('social_post.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create social post.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('social_post.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('social_post.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a social post.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('social_post.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete social posts.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
