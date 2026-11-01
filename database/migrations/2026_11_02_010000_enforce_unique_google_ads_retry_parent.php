<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateRetryParents = DB::table('google_ads_import_batches')
            ->select('retry_of_batch_id')
            ->whereNotNull('retry_of_batch_id')
            ->groupBy('retry_of_batch_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateRetryParents) {
            throw new RuntimeException('Cannot enforce unique Google Ads retry lineage while duplicate retry batches exist. Reconcile the history before applying this migration.');
        }

        Schema::table('google_ads_import_batches', function (Blueprint $table): void {
            $table->unique('retry_of_batch_id', 'ga_import_retry_parent_unique');
        });
    }

    public function down(): void
    {
        Schema::table('google_ads_import_batches', function (Blueprint $table): void {
            $table->dropUnique('ga_import_retry_parent_unique');
        });
    }
};
