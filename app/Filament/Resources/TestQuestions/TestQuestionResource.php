<?php

namespace App\Filament\Resources\TestQuestions;

use App\Models\TestQuestion;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\TestQuestions\Pages;
use App\Filament\Resources\TestQuestions\Schemas\TestQuestionForm;
use App\Filament\Resources\TestQuestions\Schemas\TestQuestionInfolist;
use App\Filament\Resources\TestQuestions\Tables\TestQuestionsTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TestQuestionResource extends Resource
{
    protected static ?string $model = TestQuestion::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return TestQuestionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TestQuestionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestQuestionsTable::configure($table);
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
            'index' => Pages\ListTestQuestions::route('/'),
            'create' => Pages\CreateTestQuestion::route('/create'),
            'edit' => Pages\EditTestQuestion::route('/{record}/edit'),
            'view' => Pages\ViewTestQuestion::route('/{record}'),
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

