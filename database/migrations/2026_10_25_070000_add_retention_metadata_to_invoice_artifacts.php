<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_invoice_artifacts', function (Blueprint $table): void {
            $table->string('retention_category', 48)->default('unclassified_evidence');
            $table->string('retention_review_status', 24)->default('pending_review');
            $table->timestamp('retention_reviewed_at')->nullable();
            $table->foreignId('retention_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('retention_policy_reference', 128)->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->text('legal_hold_reason')->nullable();
            $table->timestamp('legal_hold_at')->nullable();
            $table->foreignId('legal_hold_by')->nullable()->constrained('users')->restrictOnDelete();
        });

        DB::table('issued_invoice_artifacts')->where('artifact_type', 'invoice_html')->update(['retention_category' => 'rendered_invoice_derivative']);
        DB::table('issued_invoice_artifacts')->where('artifact_type', 'ubl_xml')->update(['retention_category' => 'structured_invoice_payload']);
        DB::table('issued_invoice_artifacts')->where('artifact_type', 'efactura_response')->update(['retention_category' => 'authority_response']);
        DB::table('issued_invoice_artifacts')->where('artifact_type', 'efactura_result_archive')->update(['retention_category' => 'authority_signed_result']);
    }

    public function down(): void
    {
        if (Schema::hasTable('issued_invoice_artifacts') && DB::table('issued_invoice_artifacts')->exists()) {
            throw new RuntimeException('Cannot drop retention metadata while invoice evidence exists.');
        }

        Schema::table('issued_invoice_artifacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('retention_reviewed_by');
            $table->dropConstrainedForeignId('legal_hold_by');
            $table->dropColumn([
                'retention_category', 'retention_review_status', 'retention_reviewed_at', 'retention_policy_reference',
                'legal_hold', 'legal_hold_reason', 'legal_hold_at',
            ]);
        });
    }
};
