<?php

namespace App\Filament\Resources\TestSubmissionAnswers;

use App\Models\TestSubmissionAnswer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestSubmissionAnswers\Pages;
use App\Filament\Resources\TestSubmissionAnswers\Schemas\TestSubmissionAnswerForm;
use App\Filament\Resources\TestSubmissionAnswers\Schemas\TestSubmissionAnswerInfolist;
use App\Filament\Resources\TestSubmissionAnswers\Tables\TestSubmissionAnswersTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use App\Filament\Clusters\Academic\AcademicCluster;
class TestSubmissionAnswerResource extends Resource
{
    protected static ?int $navigationSort = 13;
    protected static ?string $cluster = AcademicCluster::class;
    protected static string|UnitEnum|null $navigationGroup = 'Tests Management';

    protected static ?string $model = TestSubmissionAnswer::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestSubmissionAnswerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestSubmissionAnswerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestSubmissionAnswersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestSubmissionAnswers::route('/'),
            'create' => Pages\CreateTestSubmissionAnswer::route('/create'),
            'edit' => Pages\EditTestSubmissionAnswer::route('/{record}/edit'),
            'view' => Pages\ViewTestSubmissionAnswer::route('/{record}')
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}

