<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
class EmployeeEvaluationFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('job_description_id')
                    ->relationship('jobDescription', 'job_title')
                    ->required(),
                Forms\Components\KeyValue::make('criteria')
                    ->required()
            ]);
    }
}
