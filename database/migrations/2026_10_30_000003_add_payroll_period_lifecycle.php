<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->string('approval_status', 24)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('review_snapshot')->nullable();
            $table->char('review_snapshot_hash', 64)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('approved_snapshot')->nullable();
            $table->char('approved_snapshot_hash', 64)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('lifecycle_version')->default(0);
            $table->index(['approval_status', 'month'], 'employee_pay_slips_approval_period_idx');
        });

        DB::table('employee_pay_slips')->where('status', 'Closed')->update(['approval_status' => 'legacy_closed']);
        DB::table('employee_pay_slips')->where('status', 'Paid')->update(['approval_status' => 'legacy_paid']);

        Schema::create('payroll_period_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_pay_slip_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('related_pay_slip_id')->nullable()->constrained('employee_pay_slips')->restrictOnDelete();
            $table->foreignId('related_item_id')->nullable()->constrained('employee_pay_slip_items')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('note')->nullable();
            $table->char('snapshot_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['employee_pay_slip_id', 'created_at'], 'payroll_period_events_history_idx');
            $table->index(['event_type', 'created_at'], 'payroll_period_events_type_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_period_lifecycle_events') && DB::table('payroll_period_lifecycle_events')->exists()) {
            throw new RuntimeException('Payroll period lifecycle events are audit records and must be exported before rollback.');
        }
        if (Schema::hasTable('employee_pay_slips') && DB::table('employee_pay_slips')
            ->whereIn('approval_status', ['in_review', 'approved', 'closed'])
            ->orWhereNotNull('review_snapshot')
            ->orWhereNotNull('approved_snapshot')
            ->exists()) {
            throw new RuntimeException('Reviewed, approved, or closed payroll snapshots must be exported before rollback.');
        }

        Schema::dropIfExists('payroll_period_lifecycle_events');
        Schema::table('employee_pay_slips', function (Blueprint $table): void {
            $table->dropIndex('employee_pay_slips_approval_period_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn([
                'approval_status', 'reviewed_at', 'review_snapshot', 'review_snapshot_hash',
                'approved_at', 'approved_snapshot', 'approved_snapshot_hash', 'closed_at', 'lifecycle_version',
            ]);
        });
    }
};
