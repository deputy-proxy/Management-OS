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
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Hexters\HexaLite\HasHexaLite;
use AlizHarb\ActivityLog\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\HumanResources\HumanResourcesCluster;
use UnitEnum;
use App\Filament\Clusters\General\GeneralCluster;

class JobDescriptionResource extends Resource
{
    protected static ?int $navigationSort = 13;
    protected static ?string $cluster = GeneralCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Human Resources';
    use HasHexaLite;

    protected static ?string $model = JobDescription::class;

    protected static ?string $recordTitleAttribute = 'title';
    //protected static ?string $cluster = HumanResourcesCluster::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function defineGates(): array
    {
        return [
            'job_description.index' => __('Allows viewing the job description list'),
            'job_description.create' => __('Allows creating a new job description'),
            'job_description.update' => __('Allows updating job descriptions'),
            'job_description.delete' => __('Allows deleting job descriptions'),
        ];
    }

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

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('job_description.index')
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('job_description.create')
            ? Response::allow()
            : Response::deny(__('You do not have permission to create job description.'));
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('job_description.update')
            ? Response::allow()
            : Response::deny(__('You do not have permission to edit this.'));
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('job_description.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete a job description.'));
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return hexa()->can('job_description.delete')
            ? Response::allow()
            : Response::deny(__('You do not have permission to delete job descriptions.'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

