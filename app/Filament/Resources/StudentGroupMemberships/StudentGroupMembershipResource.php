<?php

namespace App\Filament\Resources\StudentGroupMemberships;

use App\Filament\Clusters\Academic\AcademicCluster;
use App\Filament\Resources\StudentGroupMemberships\Schemas\StudentGroupMembershipForm;
use App\Filament\Resources\StudentGroupMemberships\Tables\StudentGroupMembershipsTable;
use App\Models\StudentGroupMembership;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StudentGroupMembershipResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = StudentGroupMembership::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $cluster = AcademicCluster::class;

    protected static string|UnitEnum|null $navigationGroup = 'Students Management';

    protected static ?int $navigationSort = 4;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    public static function getModelLabel(): string
    {
        return 'student-group membership';
    }

    public static function getPluralModelLabel(): string
    {
        return 'student-group memberships';
    }

    public function defineGates(): array
    {
        return [
            'student-group-membership.index' => __('Allows viewing student-group memberships'),
            'student-group-membership.create' => __('Allows creating student-group memberships'),
            'student-group-membership.update' => __('Allows updating student-group memberships'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return StudentGroupMembershipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentGroupMembershipsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentGroupMemberships::route('/'),
            'create' => Pages\CreateStudentGroupMembership::route('/create'),
            'edit' => Pages\EditStudentGroupMembership::route('/{record}/edit'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return hexa()->can('student-group-membership.index') ? Response::allow() : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return hexa()->can('student-group-membership.create') ? Response::allow() : Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return hexa()->can('student-group-membership.update') ? Response::allow() : Response::deny();
    }
}