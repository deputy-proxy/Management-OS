<?php

namespace App\Filament\Resources\EmployeeEvaluations\Schemas;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\EmployeeEvaluationForm as EvaluationFormModel;

class EmployeeEvaluationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('performed_by')
                    ->relationship('performedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Performed By')
                    ->required(),

                Forms\Components\Select::make('employee_evaluation_form_id')
                    ->relationship('employeeEvaluationForm', 'id')
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => $record->jobDescription->job_title ?? "Form #{$record->id}"
                    )
                    ->searchable()
                    ->preload()
                    ->label('Evaluation Form Template')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $formId = $get('employee_evaluation_form_id');

                        if (!$formId) {
                            $set('criteria', []);
                            return;
                        }

                        $template = EvaluationFormModel::with('jobDescription')->find($formId);

                        if (!$template || empty($template->criteria)) {
                            $set('criteria', []);
                            return;
                        }

                        // Map template blocks into repeater rows for scoring
                        $rows = collect($template->criteria)->map(function ($block) {
                            return [
                                'type'        => $block['type'],
                                'label'       => $block['data']['label'] ?? $block['data']['question'] ?? null,
                                'description' => $block['data']['description'] ?? null,
                                'weight'      => $block['data']['weight'] ?? null,
                                'score'       => null,
                                'answer'      => null,
                                'notes'       => null,
                            ];
                        })->toArray();

                        $set('criteria', $rows);
                    }),

                Forms\Components\Select::make('status')
                    ->options([
                        'draft'      => 'Draft',
                        'in_progress' => 'In Progress',
                        'submitted'  => 'Submitted',
                        'approved'   => 'Approved',
                    ])
                    ->default('draft')
                    ->required(),

                Forms\Components\DatePicker::make('due_by')
                    ->required(),

                Forms\Components\DatePicker::make('performed_on'),

                Repeater::make('criteria')
    ->label('Evaluation Responses')
    ->schema([

        Select::make('type')
            ->label('Type')
            ->options([
                'rated_criterion' => 'Rated Criterion',
                'text_feedback'   => 'Text Feedback',
                'yes_no'          => 'Yes / No',
            ])
            ->disabled()
            ->dehydrated(true)
            ->live(),

        TextInput::make('label')
            ->label('Criterion')
            ->readOnly(),

        Textarea::make('description')
            ->label('Guidance')
            ->readOnly()
            ->rows(2)
            ->hidden(fn (Get $get): bool => blank($get('description'))),

        // Rated criterion → 1–5 score
        Select::make('score')
            ->label('Score (1–5)')
            ->options([
                1 => '1 – Poor',
                2 => '2 – Below Expectations',
                3 => '3 – Meets Expectations',
                4 => '4 – Exceeds Expectations',
                5 => '5 – Outstanding',
            ])
            ->hidden(fn (Get $get): bool => $get('type') !== 'rated_criterion')
            ->required(fn (Get $get): bool => $get('type') === 'rated_criterion'),

        // Yes/No question → radio
        Radio::make('answer')
            ->label('Answer')
            ->options([
                'yes' => 'Yes',
                'no'  => 'No',
            ])
            ->inline()
            ->hidden(fn (Get $get): bool => $get('type') !== 'yes_no')
            ->required(fn (Get $get): bool => $get('type') === 'yes_no'),

        // Text feedback / additional notes
        Textarea::make('notes')
            ->label(fn (Get $get): string => $get('type') === 'text_feedback' ? 'Response' : 'Additional Notes')
            ->rows(3)
            ->hidden(fn (Get $get): bool => $get('type') === 'rated_criterion' || $get('type') === 'yes_no')
            ->required(fn (Get $get): bool => $get('type') === 'text_feedback'),

        TextInput::make('weight')->hidden()->dehydrated(true),

    ])
    ->addable(false)
    ->deletable(false)
    ->reorderable(false)
    ->columnSpanFull(),

            ]);
    }
}