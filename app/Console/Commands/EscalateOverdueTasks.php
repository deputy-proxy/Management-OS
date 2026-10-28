<?php

namespace App\Console\Commands;

use App\Services\TaskEscalationService;
use Illuminate\Console\Command;

class EscalateOverdueTasks extends Command
{
    protected $signature = 'tasks:escalate-overdue';

    protected $description = 'Record deduplicated escalations for tasks that exceed the configured overdue threshold';

    public function handle(TaskEscalationService $service): int
    {
        $created = $service->escalateOverdueTasks();
        $this->info(sprintf('Created %d overdue task escalation(s).', $created));

        return self::SUCCESS;
    }
}
