<?php

namespace App\Filament\Resources\LegalDocuments;

use App\Models\LegalDocument;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\LegalDocuments\Pages;
use App\Filament\Resources\LegalDocuments\Schemas\LegalDocumentForm;
use App\Filament\Resources\LegalDocuments\Schemas\LegalDocumentInfolist;
use App\Filament\Resources\LegalDocuments\Tables\LegalDocumentsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;

class LegalDocumentResource extends Resource
{
    protected static ?int $navigationSort = 12;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Compliance Overview';
    use HasHexaLite;

    protected static ?string $model = LegalDocument::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'legal_document.index' => __('Allows viewing the legal document list'),
            'legal_document.create' => __('Allows creating a new legal document'),
            'legal_document.update' => __('Allows updating legal documents'),
            'legal_document.delete' => __('Allows deleting legal documents'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return LegalDocumentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LegalDocumentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LegalDocumentsTable::configure($table);
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
            'index' => Pages\ListLegalDocuments::route('/'),
            'create' => Pages\CreateLegalDocument::route('/create'),
            'edit' => Pages\EditLegalDocument::route('/{record}/edit'),
            'view' => Pages\ViewLegalDocument::route('/{record}'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('legal_document.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('legal_document.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create legal document.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('legal_document.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('legal_document.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a legal document.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('legal_document.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete legal documents.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
