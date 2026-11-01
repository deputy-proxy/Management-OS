<?php

namespace App\Console\Commands;

use App\Models\GoogleAdsImportBatch;
use App\Services\GoogleAdsCampaignMetricsRefreshService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RetryGoogleAdsCampaignMetricsImport extends Command
{
    protected $signature = 'market:retry-google-ads-batch {batch : Failed import batch ID}';

    protected $description = 'Retry a failed Google Ads metrics import batch when the provider marked it retryable.';

    public function handle(GoogleAdsCampaignMetricsRefreshService $refresh): int
    {
        $batch = GoogleAdsImportBatch::query()->find($this->argument('batch'));
        if (! $batch) {
            $this->error('Google Ads import batch was not found.');

            return self::INVALID;
        }

        try {
            $result = $refresh->retry($batch);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::INVALID;
        } catch (\Throwable) {
            $this->error('Google Ads batch retry failed. Inspect the new batch status and safe error code.');

            return self::FAILURE;
        }

        $this->info(sprintf('Retry batch %d created for failed batch %d (%s).', $result['retry_batch_id'], $result['batch_id'], $result['status']));

        return self::SUCCESS;
    }
}
