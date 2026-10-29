<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table): void {
            $table->foreignId('worker_engagement_id')->nullable()->after('vendor_contract_id')->constrained('worker_engagements')->restrictOnDelete();
            $table->string('workflow_status', 24)->default('unresolved')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->date('payable_on')->nullable();
            $table->foreignId('corrects_vendor_invoice_id')->nullable()->constrained('vendor_invoices')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->string('tax_treatment_status', 24)->default('unsupported');
            $table->string('tax_treatment_rule_key')->nullable();
            $table->string('tax_treatment_rule_version')->nullable();
            $table->index(['vendor_id', 'invoice_number']);
        });

        Schema::create('vendor_invoice_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['vendor_invoice_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_invoice_events') && DB::table('vendor_invoice_events')->exists()) {
            throw new RuntimeException('Cannot drop contractor invoice lifecycle history while events exist.');
        }
        Schema::dropIfExists('vendor_invoice_events');
        Schema::table('vendor_invoices', function (Blueprint $table): void {
            $table->dropForeign(['worker_engagement_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['corrects_vendor_invoice_id']);
            $table->dropIndex(['vendor_id', 'invoice_number']);
            $table->dropIndex(['workflow_status']);
            $table->dropColumn(['worker_engagement_id', 'workflow_status', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_reason', 'payable_on', 'corrects_vendor_invoice_id', 'correction_reason', 'reversal_reason', 'tax_treatment_status', 'tax_treatment_rule_key', 'tax_treatment_rule_version']);
        });
    }
};
