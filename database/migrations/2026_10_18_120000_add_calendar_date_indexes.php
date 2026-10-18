<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_templates', function (Blueprint $table): void {
            $table->index('last_revision', 'content_templates_calendar_date_index');
        });

        Schema::table('competitions', function (Blueprint $table): void {
            $table->index('start_on', 'competitions_calendar_start_index');
            $table->index('end_on', 'competitions_calendar_end_index');
        });

        Schema::table('customer_interactions', function (Blueprint $table): void {
            $table->index('timestamp', 'customer_interactions_calendar_date_index');
        });

        Schema::table('customer_invoices', function (Blueprint $table): void {
            $table->index('month', 'customer_invoices_calendar_month_index');
        });

        Schema::table('employee_evaluations', function (Blueprint $table): void {
            $table->index('due_by', 'employee_evaluations_calendar_due_index');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->index('start_time', 'events_calendar_start_index');
            $table->index('end_time', 'events_calendar_end_index');
        });

        Schema::table('scheduled_sessions', function (Blueprint $table): void {
            $table->index('starts_at', 'scheduled_sessions_calendar_start_index');
            $table->index('ends_at', 'scheduled_sessions_calendar_end_index');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_sessions', function (Blueprint $table): void {
            $table->dropIndex('scheduled_sessions_calendar_start_index');
            $table->dropIndex('scheduled_sessions_calendar_end_index');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_calendar_start_index');
            $table->dropIndex('events_calendar_end_index');
        });

        Schema::table('employee_evaluations', function (Blueprint $table): void {
            $table->dropIndex('employee_evaluations_calendar_due_index');
        });

        Schema::table('customer_invoices', function (Blueprint $table): void {
            $table->dropIndex('customer_invoices_calendar_month_index');
        });

        Schema::table('customer_interactions', function (Blueprint $table): void {
            $table->dropIndex('customer_interactions_calendar_date_index');
        });

        Schema::table('competitions', function (Blueprint $table): void {
            $table->dropIndex('competitions_calendar_start_index');
            $table->dropIndex('competitions_calendar_end_index');
        });

        Schema::table('content_templates', function (Blueprint $table): void {
            $table->dropIndex('content_templates_calendar_date_index');
        });
    }
};
