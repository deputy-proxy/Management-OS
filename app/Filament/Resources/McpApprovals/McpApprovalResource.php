<?php

namespace App\Filament\Resources\McpApprovals;

use App\Filament\Resources\McpApprovals\Pages\ListMcpApprovals;
use App\Filament\Resources\McpApprovals\Tables\McpApprovalsTable;
use App\Models\Mcp\McpApproval;
use App\Services\Mcp\McpApprovalAccess;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class McpApprovalResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = McpApproval::class;

    protected static ?string $recordTitleAttribute = 'tool_name';

    protected static ?string $navigationLabel = 'MCP approvals';

    protected static ?int $navigationSort = 90;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    public function defineGates(): array
    {
        return [
            'mcp_approval.index' => __('Allows viewing MCP operation approvals'),
            'mcp_approval.approve' => __('Allows approving or rejecting MCP operations'),
        ];
    }

    public static function table(Table $table): Table
    {
        return McpApprovalsTable::configure($table);
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
            'index' => ListMcpApprovals::route('/'),
        ];
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return app(McpApprovalAccess::class)->canViewApprovals(auth()->user())
            ? Response::allow()
            : Response::deny();
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('MCP approval requests are created by the MCP service, not by staff users.');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('MCP approval requests are immutable and may only be reviewed through the approval actions.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('MCP approval history is retained and cannot be deleted.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('MCP approval history is retained and cannot be deleted.');
    }
}
