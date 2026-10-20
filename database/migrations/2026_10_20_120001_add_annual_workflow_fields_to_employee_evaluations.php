<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table): void {
            $table->date('performed_on')->nullable()->change();
            $table->unsignedSmallInteger('evaluation_year')->nullable()->after('employee_id');
            $table->decimal('final_score', 5, 2)->nullable()->after('criteria');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('approval_deadline_at')->nullable();
            $table->string('approval_source', 40)->nullable();
            $table->text('approval_comments')->nullable();
            $table->string('approval_token_hash', 64)->nullable();
            $table->text('approval_token_encrypted')->nullable();
            $table->timestamp('approval_token_expires_at')->nullable();
            $table->timestamp('initiation_email_sent_at')->nullable();
            $table->timestamp('submission_email_sent_at')->nullable();

            $table->unique(['employee_id', 'evaluation_year'], 'employee_evaluations_employee_year_unique');
            $table->index(['status', 'approval_deadline_at'], 'employee_evaluations_timeout_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_evaluations', function (Blueprint $table): void {
            $table->dropUnique('employee_evaluations_employee_year_unique');
            $table->dropIndex('employee_evaluations_timeout_index');
            $table->dropColumn([
                'evaluation_year',
                'final_score',
                'submitted_at',
                'approved_at',
                'acknowledged_at',
                'approval_deadline_at',
                'approval_source',
                'approval_comments',
                'approval_token_hash',
                'approval_token_encrypted',
                'approval_token_expires_at',
                'initiation_email_sent_at',
                'submission_email_sent_at',
            ]);
        });
    }
};
