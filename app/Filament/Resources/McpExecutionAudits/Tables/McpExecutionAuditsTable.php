<?php

namespace App\Filament\Resources\McpExecutionAudits\Tables;

use App\Models\Mcp\McpExecutionAudit;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class McpExecutionAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('event_type')->searchable()->sortable(),
                TextColumn::make('approval_id')->label('Approval ID')->toggleable(),
                TextColumn::make('aiPrincipal.name')->label('AI principal'),
                TextColumn::make('humanInitiator.name')->label('Human initiator')->placeholder('Not known'),
                TextColumn::make('humanApprover.name')->label('Human approver')->placeholder('Not applicable'),
                TextColumn::make('tool_name')->label('Tool')->searchable(),
                TextColumn::make('tool_version')->label('Version'),
                TextColumn::make('target_type')->label('Target type'),
                TextColumn::make('target_id')->label('Target ID'),
                TextColumn::make('request_id')->label('Request ID')->toggleable(),
                TextColumn::make('idempotency_key')->toggleable(),
                TextColumn::make('payload_hash')->label('Payload hash')->limit(16)->tooltip(fn (?string $state): ?string => $state)->toggleable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('result_code')->label('Result'),
                TextColumn::make('error_code')->label('Error code'),
                TextColumn::make('domain_event_reference')->label('Domain event')->toggleable(),
                TextColumn::make('completed_at')->dateTime()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'started' => 'Started',
                    'completed' => 'Completed',
                    'succeeded' => 'Succeeded',
                    'failed' => 'Failed',
                    'uncertain' => 'Uncertain',
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'expired' => 'Expired',
                    'cancelled' => 'Cancelled',
                ]),
                SelectFilter::make('event_type')->options(fn (): array => McpExecutionAudit::query()
                    ->select('event_type')
                    ->distinct()
                    ->orderBy('event_type')
                    ->pluck('event_type', 'event_type')
                    ->all()),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
