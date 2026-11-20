<?php

namespace App\Filament\Resources\McpApprovals\Pages;

use App\Filament\Resources\McpApprovals\McpApprovalResource;
use Filament\Resources\Pages\ListRecords;

final class ListMcpApprovals extends ListRecords
{
    protected static string $resource = McpApprovalResource::class;
}
