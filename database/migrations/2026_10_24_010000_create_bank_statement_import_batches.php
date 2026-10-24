<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('bank_code', 32);
            $table->string('source_sha256', 64);
            $table->string('parser_version', 64);
            $table->string('status', 24)->default('previewed');
            $table->string('currency_code', 3);
            $table->date('statement_start');
            $table->date('statement_end');
            $table->unsignedInteger('transaction_count')->default(0);
            $table->unsignedInteger('review_required_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->json('preview_payload');
            $table->json('validation_summary')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['financial_account_id', 'source_sha256'], 'bank_statement_batch_account_source_unique');
            $table->index(['status', 'created_at']);
        });

        Schema::create('bank_statement_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('bank_statement_import_batches')->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained()->restrictOnDelete();
            $table->string('line_fingerprint', 64);
            $table->date('transaction_date');
            $table->string('direction', 16);
            $table->decimal('amount_decimal', 19, 2);
            $table->char('currency_code', 3);
            $table->string('transaction_type_code', 64)->nullable();
            $table->string('classification_status', 32);
            $table->text('description');
            $table->string('operation_reference', 128)->nullable();
            $table->string('document_reference', 128)->nullable();
            $table->string('source_sha256', 64);
            $table->string('parser_version', 64);
            $table->unsignedInteger('source_page');
            $table->unsignedInteger('source_row');
            $table->json('review_reasons')->nullable();
            $table->timestamps();
            $table->unique(['financial_account_id', 'line_fingerprint'], 'bank_statement_item_account_fingerprint_unique');
            $table->unique(['batch_id', 'source_page', 'source_row'], 'bank_statement_item_batch_source_row_unique');
        });

        Schema::create('bank_statement_import_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('bank_statement_import_batches')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['batch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_statement_import_events') && DB::table('bank_statement_import_events')->exists()) {
            throw new RuntimeException('Cannot remove bank statement import audit history once events exist.');
        }

        if (Schema::hasTable('bank_statement_import_batches') && DB::table('bank_statement_import_batches')->exists()) {
            throw new RuntimeException('Cannot remove bank statement import batches once batch history exists.');
        }

        Schema::dropIfExists('bank_statement_import_events');
        Schema::dropIfExists('bank_statement_import_items');
        Schema::dropIfExists('bank_statement_import_batches');
    }
};
