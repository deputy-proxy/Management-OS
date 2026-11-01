<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_data_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source_key', 80);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_imported')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->string('error_type', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_retryable')->default(false);
            $table->json('request_metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source_key', 'status', 'started_at'], 'market_data_import_runs_source_status_idx');
        });

        Schema::table('market_trends', function (Blueprint $table): void {
            $table->string('source_idempotency_key', 255)->nullable()->unique('market_trends_source_idempotency_unique');
            $table->foreignId('source_import_run_id')->nullable()->constrained('market_data_import_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('market_trends', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_import_run_id');
            $table->dropUnique('market_trends_source_idempotency_unique');
            $table->dropColumn('source_idempotency_key');
        });

        Schema::dropIfExists('market_data_import_runs');
    }
};
