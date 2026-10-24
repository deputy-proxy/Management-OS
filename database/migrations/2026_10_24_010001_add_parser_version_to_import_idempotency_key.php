<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The composite source key may be the index MySQL selected to support the account FK.
        // Add a dedicated FK index before replacing that composite key.
        Schema::table('bank_statement_import_batches', function (Blueprint $table): void {
            $table->index('financial_account_id', 'bank_statement_batch_account_fk_index');
        });

        Schema::table('bank_statement_import_batches', function (Blueprint $table): void {
            $table->dropUnique('bank_statement_batch_account_source_unique');
            $table->unique(
                ['financial_account_id', 'source_sha256', 'parser_version'],
                'bank_statement_batch_account_source_parser_unique',
            );
        });
    }

    public function down(): void
    {
        $hasCrossVersionDuplicates = DB::table('bank_statement_import_batches')
            ->select(['financial_account_id', 'source_sha256'])
            ->groupBy('financial_account_id', 'source_sha256')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasCrossVersionDuplicates) {
            throw new RuntimeException('Cannot remove parser-version idempotency while batches exist for the same file under multiple parser versions.');
        }

        Schema::table('bank_statement_import_batches', function (Blueprint $table): void {
            $table->dropUnique('bank_statement_batch_account_source_parser_unique');
            $table->unique(['financial_account_id', 'source_sha256'], 'bank_statement_batch_account_source_unique');
        });
    }
};
