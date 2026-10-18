<?php

namespace App\Filament\Resources\SessionAttendances\Schemas;

use App\Models\Session;
use Filament\Forms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SessionAttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('session_id')
                    ->relationship('session', 'identifier', fn (Builder $query) => $query->where('session_type', Session::TYPE_GROUP))
                    ->required(),
                Forms\Components\Select::make('student_id')
                    ->relationship('student', 'full_name')
                    ->required(),
            ]);
    }
}
