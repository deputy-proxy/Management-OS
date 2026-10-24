<?php

namespace App\Console\Commands;

use App\Services\FinancialNormalizationService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Throwable;

class NormalizeFinancialData extends Command
{
    protected $signature = 'finance:normalize-data {--apply : Persist approved canonical mappings and audit outcomes; default is dry-run} {--batch= : Rows per stable primary-key batch (1-1000)}';

    protected $description = 'Dry-run or apply only explicitly approved financial normalization mappings.';

    public function handle(FinancialNormalizationService $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $batchSize = (int) ($this->option('batch') ?: config('financial-normalization.batch_size', 200));

        $this->info($apply ? 'Applying approved financial mappings in per-record transactions.' : 'Dry run: no records or audit tables will be changed.');

        try {
            $summary = $normalizer->run($apply, $batchSize);
        } catch (Throwable $exception) {
            $runId = $normalizer->runId();
            $message = str_contains(strtoupper($exception->getMessage()), 'SQLSTATE') || $exception instanceof QueryException
                ? 'Database operation failed; inspect restricted application logs for details.'
                : $exception->getMessage();
            $this->error('Financial normalization failed'.($runId ? " in run #{$runId}" : '').': '.$message);
            $this->line('Earlier committed records, if any, remain auditable. Re-run after correcting the cause; existing canonical values are never overwritten silently.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Record outcomes by table:');
        foreach ($summary['record_outcomes'] as $table => $counts) {
            $this->line(sprintf('%s: changed=%d, unchanged=%d, rejected=%d, quarantined=%d', $table, $counts['changed'], $counts['unchanged'], $counts['rejected'], $counts['quarantined']));
        }

        $totals = ['changed' => 0, 'unchanged' => 0, 'rejected' => 0, 'quarantined' => 0];
        foreach ($summary['record_outcomes'] as $counts) {
            foreach ($totals as $outcome => $unused) {
                $totals[$outcome] += $counts[$outcome];
            }
        }
        $this->info(sprintf('Totals: changed=%d, unchanged=%d, rejected=%d, quarantined=%d', $totals['changed'], $totals['unchanged'], $totals['rejected'], $totals['quarantined']));

        $reconciliation = $summary['reconciliation'];
        if ($apply) {
            $this->info(sprintf('Reconciliation: %s; groups=%d; mismatches=%d; unresolved currency/classification rows=%d', $reconciliation['status'], count($reconciliation['groups']), $reconciliation['mismatches'], $reconciliation['unresolved_currency_or_classification_rows']));
            foreach ($reconciliation['groups'] as $group => $totalsByGroup) {
                $this->line(sprintf('%s: rows=%d; expected=%s; actual=%s; %s', $group, $totalsByGroup['rows'], $totalsByGroup['expected_total'], $totalsByGroup['actual_total'], $totalsByGroup['matches'] ? 'MATCH' : 'MISMATCH'));
            }
        } else {
            $this->line('Reconciliation: dry-run only; post-write totals were not computed.');
        }

        $this->line('Field outcomes:');
        foreach ($summary['field_outcomes'] as $field => $counts) {
            $this->line(sprintf('%s: changed=%d, unchanged=%d, rejected=%d, quarantined=%d', $field, $counts['changed'], $counts['unchanged'], $counts['rejected'], $counts['quarantined']));
        }

        if ($apply) {
            $this->info('Audit run #'.$normalizer->runId().' completed. Source fields remain unchanged; inspect financial_normalization_items for per-field evidence.');
        } else {
            $this->line('Dry-run results are not persisted. Configure exact approved mappings before applying any backfill.');
        }

        return $totals['rejected'] > 0 || ($apply && $reconciliation['mismatches'] > 0) ? self::FAILURE : self::SUCCESS;
    }
}
