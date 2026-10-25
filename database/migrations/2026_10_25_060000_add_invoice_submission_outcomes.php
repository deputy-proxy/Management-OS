<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_submissions', function (Blueprint $table): void {
            $table->string('download_id', 128)->nullable();
            $table->string('download_state', 24)->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->foreignId('result_artifact_id')->nullable()->constrained('issued_invoice_artifacts')->restrictOnDelete();
            $table->foreignId('corrects_submission_id')->nullable()->constrained('invoice_submissions')->restrictOnDelete();
        });

        Schema::create('invoice_submission_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_submission_id')->constrained('invoice_submissions')->restrictOnDelete();
            $table->foreignId('invoice_submission_attempt_id')->nullable()->constrained('invoice_submission_attempts')->restrictOnDelete();
            $table->foreignId('response_artifact_id')->nullable()->constrained('issued_invoice_artifacts')->restrictOnDelete();
            $table->string('source', 24);
            $table->string('authority_code', 128)->nullable();
            $table->string('severity', 16)->default('error');
            $table->string('field_path', 512)->nullable();
            $table->char('message_sha256', 64);
            $table->char('source_response_sha256', 64);
            $table->string('retry_classification', 24);
            $table->text('safe_summary');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['invoice_submission_id', 'source_response_sha256', 'message_sha256'], 'invoice_submission_errors_dedupe_unique');
            $table->index(['invoice_submission_id', 'severity'], 'invoice_submission_errors_submission_severity_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_submission_errors') && DB::table('invoice_submission_errors')->exists()) {
            throw new RuntimeException('Cannot drop e-Factura authority error history while records exist.');
        }
        if (Schema::hasTable('invoice_submissions') && DB::table('invoice_submissions')->exists()) {
            throw new RuntimeException('Cannot drop e-Factura outcome fields while submissions exist.');
        }

        Schema::dropIfExists('invoice_submission_errors');
        if (Schema::hasTable('invoice_submissions')) {
            Schema::table('invoice_submissions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('result_artifact_id');
                $table->dropConstrainedForeignId('corrects_submission_id');
                $table->dropColumn(['download_id', 'download_state', 'downloaded_at']);
            });
        }
    }
};
