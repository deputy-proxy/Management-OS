<?php

namespace App\Filament\Resources\EmployeeEvaluations\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class EmployeeEvaluationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('employee.full_name')->label('Employee'),
                TextEntry::make('evaluation_year')->label('Evaluation Year'),
                TextEntry::make('final_score')->label('Final Score')->suffix('%'),
                KeyValueEntry::make('criteria'),
                TextEntry::make('due_by')->date(),
                TextEntry::make('performed_on')->date(),
                TextEntry::make('performedBy.name')->label('Evaluator'),
                TextEntry::make('employeeEvaluationForm.jobDescription.job_title')->label('Job Description'),
                TextEntry::make('submitted_at')->dateTime(),
                TextEntry::make('approved_at')->dateTime(),
                TextEntry::make('acknowledged_at')->dateTime(),
                TextEntry::make('approval_source')->label('Approval Outcome'),
                TextEntry::make('approval_comments')->label('Employee Comments'),
                TextEntry::make('status'),
            ]);
    }
}
