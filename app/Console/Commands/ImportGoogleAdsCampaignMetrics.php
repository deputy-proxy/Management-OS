<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsImportConfiguration;
use App\Services\GoogleAdsCampaignMetricsImporter;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportGoogleAdsCampaignMetrics extends Command
{
    protected $signature = 'market:import-google-ads {--from= : First date YYYY-MM-DD} {--to= : Last date YYYY-MM-DD} {--configuration= : Saved Google Ads import configuration ID} {--dry-run : Fetch and validate without writing rows}';

    protected $description = 'Import daily Google Ads campaign metrics for a bounded date range.';

    public function handle(GoogleAdsCampaignMetricsImporter $importer): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');
        if ($from === '' || $to === '') {
            $this->error('Both --from and --to are required.');

            return self::INVALID;
        }

        $configuration = null;
        if ($this->option('configuration') !== null) {
            $configuration = GoogleAdsImportConfiguration::query()->find($this->option('configuration'));
            if (! $configuration) {
                $this->error('Google Ads import configuration was not found.');

                return self::INVALID;
            }
        }

        try {
            $result = $importer->import($from, $to, (bool) $this->option('dry-run'), $configuration);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::INVALID;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Google Ads import failed. See restricted logs for safe diagnostic metadata. No partial import was committed.');

            return self::FAILURE;
        }

        $verb = $result['dry_run'] ? 'Validated' : 'Imported';
        $this->info(sprintf('%s %d campaign-day rows for customer %s (%s through %s).', $verb, $result['row_count'], $result['customer_id'], $result['from'], $result['to']));
        if ($result['dry_run']) {
            $this->warn('Dry run: no rows were written.');
        }

        return self::SUCCESS;
    }
}
