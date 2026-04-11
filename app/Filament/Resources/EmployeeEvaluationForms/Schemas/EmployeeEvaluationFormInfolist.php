<?php

namespace App\Filament\Resources\EmployeeEvaluationForms\Schemas;

use Filament\Infolists;
use Filament\Schemas\Schema;
class EmployeeEvaluationFormInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Infolists\Components\TextEntry::make('job_description_id'),
                \Filament\Infolists\Components\KeyValueEntry::make('criteria')
            ]);
    }
}
