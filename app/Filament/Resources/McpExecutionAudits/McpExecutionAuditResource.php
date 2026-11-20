<?php

namespace App\Filament\Resources\McpExecutionAudits;

use App\Filament\Resources\McpExecutionAudits\Pages\ListMcpExecutionAudits;
use App\Filament\Resources\McpExecutionAudits\Tables\McpExecutionAuditsTable;
use App\Models\Mcp\McpExecutionAudit;
use App\Services\Mcp\McpApprovalAccess;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class McpExecutionAuditResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = McpExecutionAudit::class;

    protected static ?string $recordTitleAttribute = 'event_type';

    protected static ?string $navigationLabel = 'MCP execution audit';

    protected static ?int $navigationSort = 91;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public function defineGates(): array
    {
        return [
            'mcp_execution_audit.index' => __('Allows viewing MCP execution audit history'),
        ];
    }

    public static function table(Table $table): Table
    {
        return McpExecutionAuditsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'aiPrincipal:id,name,email',
            'humanInitiator:id,name,email',
            'humanApprover:id,name,email',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMcpExecutionAudits::route('/'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return app(McpApprovalAccess::class)->canViewExecutionAudit(auth()->user())
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('MCP audit events are created by the MCP services.');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('MCP execution audit history is read-only.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('MCP execution audit history is retained and cannot be deleted.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('MCP execution audit history is retained and cannot be deleted.');
    }
}
