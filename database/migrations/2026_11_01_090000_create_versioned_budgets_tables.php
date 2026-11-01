<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('budget_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('period_start');
            $table->date('period_end');
            $table->char('currency_code', 3);
            $table->json('dimensions');
            $table->string('status', 16)->default('draft');
            $table->string('change_summary', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['budget_id', 'version'], 'budget_version_number_unique');
            $table->index(['status', 'period_start', 'period_end'], 'budget_version_period_status_idx');
            $table->index(['currency_code', 'status'], 'budget_version_currency_status_idx');
        });
        Schema::create('budget_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_version_id')->constrained()->restrictOnDelete();
            $table->string('code', 64)->nullable();
            $table->string('description', 200);
            $table->decimal('amount', 19, 2);
            $table->json('dimensions')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['budget_version_id', 'position'], 'budget_line_item_order_idx');
        });
        Schema::create('budget_version_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('budget_version_id')->constrained()->restrictOnDelete();
            $table->string('event', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['budget_version_id', 'created_at'], 'budget_version_event_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_version_events');
        Schema::dropIfExists('budget_line_items');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('budgets');
    }
};
