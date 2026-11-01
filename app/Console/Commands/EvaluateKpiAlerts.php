<?php

namespace App\Console\Commands;

use App\Services\KpiAlertService;
use Illuminate\Console\Command;

class EvaluateKpiAlerts extends Command
{
    protected $signature = 'kpi:alerts-evaluate';

    protected $description = 'Evaluate enabled KPI threshold alerts using permission-scoped, fresh KPI results.';

    public function handle(KpiAlertService $alerts): int
    {
        $count = $alerts->evaluateConfiguredAlerts();
        $this->info("Evaluated {$count} configured KPI alert rules.");

        return self::SUCCESS;
    }
}
