<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_ads_import_batches', function (Blueprint $table): void {
            $table->foreignId('retry_of_batch_id')->nullable();
            $table->foreign('retry_of_batch_id', 'ga_import_retry_parent_fk')
                ->references('id')->on('google_ads_import_batches')->nullOnDelete();
            $table->unsignedInteger('rows_received')->default(0);
            $table->unsignedInteger('rows_inserted')->default(0);
            $table->unsignedInteger('rows_updated')->default(0);
            $table->unsignedInteger('rows_unchanged')->default(0);
            $table->boolean('retryable')->default(false);
            $table->unsignedSmallInteger('retry_attempt')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->index(['status', 'retryable', 'started_at'], 'ga_batches_retry_status_idx');
        });
    }

    public function down(): void
    {
        if (DB::table('google_ads_import_batches')->whereNotNull('retry_of_batch_id')->exists()) {
            throw new RuntimeException('Cannot remove Google Ads retry lineage once it exists.');
        }

        Schema::table('google_ads_import_batches', function (Blueprint $table): void {
            $table->dropForeign('ga_import_retry_parent_fk');
            $table->dropIndex('ga_batches_retry_status_idx');
            $table->dropColumn([
                'retry_of_batch_id', 'rows_received', 'rows_inserted', 'rows_updated',
                'rows_unchanged', 'retryable', 'retry_attempt', 'next_retry_at', 'failure_message',
            ]);
        });
    }
};
