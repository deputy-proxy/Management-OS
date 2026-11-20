<?php

namespace App\Filament\Resources\McpApprovals\Tables;

use App\Models\Mcp\McpApproval;
use App\Models\User;
use App\Services\Mcp\McpApprovalAccess;
use App\Services\Mcp\McpApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class McpApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Approval')->sortable(),
                TextColumn::make('tool_name')->label('Operation')->searchable()->sortable(),
                TextColumn::make('aiPrincipal.name')->label('AI principal'),
                TextColumn::make('target_type')->label('Target type')->placeholder('Not specified'),
                TextColumn::make('target_id')->label('Target ID')->placeholder('Not specified'),
                TextColumn::make('preview_summary')->label('Requested operation')->wrap(),
                TextColumn::make('decision_reason')->label('Review rationale')->wrap()->placeholder('Not reviewed')->toggleable(),
                TextColumn::make('cancellation_reason')->label('Cancellation reason')->wrap()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
                TextColumn::make('payload_hash')->label('Payload hash')->limit(16)->tooltip(fn (?string $state): ?string => $state),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                TextColumn::make('humanApprover.name')->label('Reviewed by')->placeholder('Not reviewed'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    McpApproval::STATUS_PENDING => 'Pending',
                    McpApproval::STATUS_APPROVED => 'Approved',
                    McpApproval::STATUS_REJECTED => 'Rejected',
                    McpApproval::STATUS_EXPIRED => 'Expired',
                    McpApproval::STATUS_CANCELLED => 'Cancelled',
                    McpApproval::STATUS_EXECUTING => 'Executing',
                    McpApproval::STATUS_EXECUTED => 'Executed',
                    McpApproval::STATUS_EXECUTION_BLOCKED => 'Execution blocked',
                    McpApproval::STATUS_EXECUTION_UNCERTAIN => 'Execution uncertain',
                ]),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (McpApproval $record): bool => $record->status === McpApproval::STATUS_PENDING
                        && app(McpApprovalAccess::class)->canReview(auth()->user()))
                    ->form([
                        Textarea::make('reason')
                            ->label('Approval rationale')
                            ->required()
                            ->minLength(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (McpApproval $record, array $data, McpApprovalService $service): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $service->approve($record, $actor, $data['reason']);
                    })
                    ->successNotificationTitle('MCP operation approved'),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (McpApproval $record): bool => $record->status === McpApproval::STATUS_PENDING
                        && app(McpApprovalAccess::class)->canReview(auth()->user()))
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection rationale')
                            ->required()
                            ->minLength(3)
                            ->maxLength(2000),
                    ])
                    ->action(function (McpApproval $record, array $data, McpApprovalService $service): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $service->reject($record, $actor, $data['reason']);
                    })
                    ->successNotificationTitle('MCP operation rejected'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
