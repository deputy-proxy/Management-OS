<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $canonicalColumns = [
        'transactions' => ['transaction_type_code'],
        'customer_invoices' => ['status_code'],
        'vendor_invoices' => ['status_code'],
        'taxes' => ['status_code'],
    ];

    public function up(): void
    {
        foreach ($this->canonicalColumns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->string($column, 64)->nullable();
                }
            });
        }

        Schema::create('financial_normalization_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('mode', 16);
            $table->string('status', 16);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_normalization_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('financial_normalization_runs')->restrictOnDelete();
            $table->string('source_table', 64);
            $table->unsignedBigInteger('source_id');
            $table->string('field_name', 64);
            $table->string('outcome', 16);
            $table->string('record_outcome', 16);
            $table->longText('source_value')->nullable();
            $table->text('target_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'source_table', 'source_id', 'field_name'], 'financial_normalization_items_run_source_field_unique');
            $table->index(['source_table', 'source_id']);
            $table->index(['outcome']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_normalization_runs') && DB::table('financial_normalization_runs')->exists()) {
            throw new RuntimeException('Cannot roll back financial normalization schema while normalization run/audit history exists.');
        }

        foreach ($this->canonicalColumns as $tableName => $columns) {
            foreach ($columns as $column) {
                if (DB::table($tableName)->whereNotNull($column)->exists()) {
                    throw new RuntimeException("Cannot roll back {$tableName}.{$column} while canonical classifications are populated.");
                }
            }
        }

        Schema::dropIfExists('financial_normalization_items');
        Schema::dropIfExists('financial_normalization_runs');

        foreach ($this->canonicalColumns as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
