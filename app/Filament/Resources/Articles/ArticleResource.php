<?php

namespace App\Filament\Resources\Articles;

use App\Models\Article;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Articles\Pages;
use App\Filament\Resources\Articles\Schemas\ArticleForm;
use App\Filament\Resources\Articles\Schemas\ArticleInfolist;
use App\Filament\Resources\Articles\Tables\ArticlesTable;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\Marketing\MarketingCluster;

class ArticleResource extends Resource
{
    protected static ?string $cluster = MarketingCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Content Management';
	use HasHexaLite;
    
    protected static ?string $model = Article::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'article.index' => __('Allows viewing the article list'),
            'article.create' => __('Allows creating a new article'),
            'article.update' => __('Allows updating articles'),
            'article.delete' => __('Allows deleting articles'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
	{
 	   return static::getModel()::count();
	}

    public static function form(Schema $schema): Schema
    {
        return ArticleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ArticleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArticlesTable::configure($table);
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
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
            'view' => Pages\ViewArticle::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('article.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('article.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create article.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('article.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('article.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete an article.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('article.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete articles.'));
    }
}

