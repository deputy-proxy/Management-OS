<?php

namespace App\Console\Commands;

use App\Services\ProductSyncService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackfillProducts extends Command
{
    protected $signature = 'products:backfill {--apply : Create missing products; without this option the command only reports changes}';

    protected $description = 'Report or create missing Products for existing camps, courses, events, and workshops.';

    public function handle(ProductSyncService $products): int
    {
        $dryRun = ! $this->option('apply');
        $missing = 0;
        $existing = 0;

        foreach (array_keys(ProductSyncService::PRODUCTABLE_MODELS) as $modelClass) {
            /** @var class-string<Model> $modelClass */
            $modelClass::query()->withTrashed()->orderBy('id')->chunkById(200, function ($models) use ($products, $modelClass, $dryRun, &$missing, &$existing): void {
                foreach ($models as $model) {
                    $type = ProductSyncService::PRODUCTABLE_MODELS[$modelClass];
                    $count = DB::table('products')
                        ->where('productable_type', $type)
                        ->where('productable_id', $model->getKey())
                        ->count();

                    if ($count > 1) {
                        $this->warn(sprintf('Duplicate products found for %s #%s (%d records); left unchanged.', class_basename($modelClass), $model->getKey(), $count));

                        continue;
                    }

                    if ($count === 1) {
                        $existing++;
                        if (! $dryRun && ! $model->trashed()) {
                            $products->sync($model);
                        }

                        continue;
                    }

                    $missing++;
                    if (! $dryRun && ! $model->trashed()) {
                        $products->sync($model);
                    }
                }
            });
        }

        $this->info(sprintf('%s: %d missing product(s), %d existing product link(s).', $dryRun ? 'Dry run' : 'Backfill complete', $missing, $existing));
        if ($dryRun) {
            $this->line('Run with --apply to create missing products.');
        }

        return self::SUCCESS;
    }
}
