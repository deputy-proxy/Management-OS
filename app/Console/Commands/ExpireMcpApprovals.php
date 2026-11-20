<?php

namespace App\Console\Commands;

use App\Services\Mcp\McpApprovalService;
use Illuminate\Console\Command;

final class ExpireMcpApprovals extends Command
{
    protected $signature = 'mcp:expire-approvals';

    protected $description = 'Expire pending MCP approvals and mark interrupted executions uncertain';

    public function handle(McpApprovalService $approvals): int
    {
        $expired = $approvals->expirePending();
        $uncertain = $approvals->markStaleExecutionsUncertain();

        $this->components->info("Expired {$expired} pending MCP approval(s); marked {$uncertain} interrupted execution(s) uncertain.");

        return self::SUCCESS;
    }
}
