<?php

namespace App\Console\Commands;

use App\Services\AnnualEmployeeEvaluationGenerator;
use Illuminate\Console\Command;

class GenerateAnnualEmployeeEvaluations extends Command
{
    protected $signature = 'evaluations:generate-annual {--year= : Evaluation year (deadline is March 31 of the following year)} {--dry-run : Preview records and configuration issues without writing data or sending email}';

    protected $description = 'Generate annual employee evaluations for the previous calendar year or a selected year';

    public function handle(AnnualEmployeeEvaluationGenerator $generator): int
    {
        $yearOption = $this->option('year');
        $year = $yearOption === null ? now()->year - 1 : filter_var($yearOption, FILTER_VALIDATE_INT);

        if (! is_int($year) || $year < 2000 || $year > 9998) {
            $this->error('The evaluation year must be a valid year between 2000 and 9998.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(sprintf('%s annual evaluations for evaluation year %d (deadline: March 31, %d).', $dryRun ? 'Previewing' : 'Generating', $year, $year + 1));

        $report = $generator->generate($year, $dryRun);

        $this->table(
            ['Result', 'Count'],
            [
                ['Created / would create', $report['created']],
                ['Already existed', $report['existing']],
                ['Skipped due to configuration', $report['skipped']],
                ['Initiation emails sent', $report['notified']],
            ],
        );

        foreach ($report['errors'] as $error) {
            $this->warn($error);
        }

        if ($report['errors'] !== []) {
            $this->warn(sprintf('%d record(s) require attention.', count($report['errors'])));
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
