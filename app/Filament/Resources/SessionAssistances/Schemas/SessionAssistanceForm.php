<?php

namespace App\Filament\Resources\SessionAssistances\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class SessionAssistanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('group_session_id')
                    ->relationship('groupSession', 'id')
                    ->required(),
                Forms\Components\Select::make('teacher_id')
                    ->relationship('teacher', 'full_name')
                    ->required(),
                Forms\Components\DatePicker::make('assistance_date')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Builder::make('review')
                    ->blocks([
                        Block::make('criterion')
                            ->schema([
                                Select::make('criterion')
                                    ->options([
                                        'excellent' => 'Excellent',
                                        'good' => 'Good',
                                        'average' => 'Average',
                                        'poor' => 'Poor',
                                    ])
                                    ->label('Criterion')
                                    ->required()
                                    ->columnSpan(1),
                                Select::make('grade')
                                    ->options([
                                        '5' => 'Excellent',
                                        '4' => 'Good',
                                        '3' => 'Average',
                                        '2' => 'Poor',
                                        '1' => 'Very Poor',
                                    ])
                                    ->label('Grade')
                                    ->required()
                                    ->columnSpan(1),
                                Textarea::make('comment')
                                    ->label('Comment')
                                    ->required()
                                    ->columnSpan(4),
                            ])
                            ->columns(6)
                    ])
                    ->required(),
            ]);
    }
}
