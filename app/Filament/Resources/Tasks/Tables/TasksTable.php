<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Filament\Resources\Tasks\Actions\TasksAction;
use App\Models\Task;
use App\Services\TaskEscalationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables;
use Filament\Tables\Table;
use Relaticle\Comments\Filament\Actions\CommentsAction;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Assigned To'),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Project')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('projectPhase.title')
                    ->label('Project Phase')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('dependencies.action')->label('Prerequisites')->listWithLineBreaks()->limitList(3),
                Tables\Columns\TextColumn::make('escalations.status')
                    ->label('Escalations')
                    ->listWithLineBreaks()
                    ->placeholder('None'),
                Tables\Columns\TextColumn::make('dependency_readiness')
                    ->label('Dependency readiness')
                    ->description(fn (Task $record): ?string => $record->dependency_blocked_reasons === []
                        ? null
                        : 'Waiting for: '.implode(', ', $record->dependency_blocked_reasons))
                    ->badge()
                    ->color(fn (string $state): string => str_starts_with($state, 'Blocked') ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('due_date')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('estimated_effort_minutes')
                    ->label('Effort (min)')
                    ->placeholder('Not estimated')
                    ->numeric(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By'),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('acknowledge_escalation')
                    ->label('Acknowledge escalation')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->visible(fn (Task $record): bool => $record->escalations()->where('status', 'open')->exists())
                    ->requiresConfirmation()
                    ->action(function (Task $record, TaskEscalationService $service): void {
                        $escalation = $record->escalations()->where('status', 'open')->orderBy('escalated_at')->first();
                        if ($escalation !== null) {
                            $service->acknowledge($escalation);
                        }
                    }),
                TasksAction::make(),
                CommentsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
